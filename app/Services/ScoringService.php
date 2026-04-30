<?php

namespace App\Services;

use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\PerformanceScore;
use App\Models\PeriodTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    /**
     * Recalculate all scores for a user for a specific month.
     */
    public static function updateMonthScores(User $user, $monthStr)
    {
        $month = Carbon::parse($monthStr);
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();
        
        $metrics = Metric::all();

        // Define our progressive periods for tracking growth
        $periods = [
            [
                'type' => '10_days',
                'start' => $startOfMonth->copy(),
                'end' => $startOfMonth->copy()->addDays(9)
            ],
            [
                'type' => '20_days',
                'start' => $startOfMonth->copy(), // Starts from day 1
                'end' => $startOfMonth->copy()->addDays(19)
            ],
            [
                'type' => '30_days',
                'start' => $startOfMonth->copy(), // Starts from day 1 (Consolidated)
                'end' => $endOfMonth->copy()
            ],
            [
                'type' => 'monthly',
                'start' => $startOfMonth->copy(),
                'end' => $endOfMonth->copy()
            ]
        ];

        foreach ($metrics as $metric) {
            foreach ($periods as $p) {
                self::calculatePeriodScore($user, $metric, $p['type'], $p['start'], $p['end']);
            }
        }
    }

    private static function calculatePeriodScore(User $user, Metric $metric, $type, $start, $end)
    {
        // 1. Get approved slips for this user, metric, and date range
        $data = Slip::where('user_id', $user->id)
            ->where('metric_id', $metric->id)
            ->where('status', 'approved')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select(
                DB::raw('SUM(value) as total_value'),
                DB::raw('SUM(daily_points_earned) as total_daily_points')
            )
            ->first();

        $totalValue = (float) ($data->total_value ?? 0);
        $dailyPointsSum = (float) ($data->total_daily_points ?? 0);

        // Calculate percentage against reference metric if needed
        if ($metric->value_type === 'percentage' && $metric->reference_metric_id) {
            $refData = Slip::where('user_id', $user->id)
                ->where('metric_id', $metric->reference_metric_id)
                ->where('status', 'approved')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->select(DB::raw('SUM(value) as total_ref_value'))
                ->first();
                
            $totalRefValue = (float) ($refData->total_ref_value ?? 0);
            
            if ($totalRefValue > 0) {
                $totalValue = ($totalValue / $totalRefValue) * 100;
            } else {
                $totalValue = 0;
            }
        }

        // 2. Find target tiers for this period (Filter by user's role)
        $roleId = $user->roles()->first()?->id;
        
        $targets = PeriodTarget::where('metric_id', $metric->id)
            ->where('period_type', $type)
            ->when($roleId, fn($q) => $q->where('role_id', $roleId))
            ->orderBy('min_value', 'desc')
            ->get();

        // Fallback: If calculating 30_days and no targets found for role, check 'monthly'
        if ($targets->isEmpty() && $type === '30_days') {
            $targets = PeriodTarget::where('metric_id', $metric->id)
                ->where('period_type', 'monthly')
                ->when($roleId, fn($q) => $q->where('role_id', $roleId))
                ->orderBy('min_value', 'desc')
                ->get();
        }

        $periodPoints = 0;
        $achievedTier = 'grey';
        foreach ($targets as $target) {
            if ($totalValue >= $target->min_value) {
                $periodPoints = (float) $target->points_awarded;
                $achievedTier = $target->tier_label;
                break;
            }
        }

        // 3. Save Score
        PerformanceScore::updateOrCreate(
            [
                'user_id' => $user->id,
                'metric_id' => $metric->id,
                'period_type' => $type,
                'period_start' => $start->toDateString(),
            ],
            [
                'period_end' => $end->toDateString(),
                'cumulative_value' => $totalValue,
                'daily_points_sum' => $dailyPointsSum,
                'period_points_earned' => $periodPoints,
                'traffic_light' => $achievedTier,
            ]
        );
    }
}
