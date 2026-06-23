<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\Attendance;
use App\Models\PerformanceScore;
use App\Models\SummaryReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Individual Employee Excel-style Report
     */
    public function individual(Request $request)
    {
        $userId = $request->user_id;
        $user   = $userId ? User::find($userId) : null;
        
        // Only auto-load for non-admins if no user_id provided
        if (!$userId && !auth()->user()->hasAnyPermission(['approve slips', 'configure metrics'])) {
            $user = auth()->user();
        }

        $allEmployees = [];
        if (auth()->user()->hasAnyPermission(['approve slips', 'configure metrics'])) {
            $allEmployees = User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))
                ->orderBy('name')
                ->get();
        }

        $period = $request->period ?? 'this_month';
        $dateFrom = $request->date_from ?? $request->from; 
        $dateTo   = $request->date_to   ?? $request->to;

        if (!$dateFrom || !$dateTo) {
            $monthStr = $request->month ? Carbon::parse($request->month)->format('Y-m') : Carbon::today()->format('Y-m');
            [$dateFrom, $dateTo] = $this->resolvePeriod($period, $monthStr);
        }
        
        $carbonFrom = Carbon::parse($dateFrom);
        $monthStr   = $carbonFrom->format('Y-m');
        $daysInMonth = $carbonFrom->daysInMonth;

        $metrics = [];
        $slips = [];
        $attendance = [];
        $scores = [];

        if ($user) {
            $user->load('roles');
            $userRoleIds = $user->roles->pluck('id');
            // Get all metrics for this user's role, eagerly loading scoped tiers
            $metrics = Metric::with([
                'periodTargets' => fn($q) => $q->whereIn('role_id', $userRoleIds),
                'dailyScoringTiers' => fn($q) => $q->whereIn('role_id', $userRoleIds),
            ])->whereHas('roles', fn($q) => $q->whereIn('roles.id', $userRoleIds))->get();

            // Get all approved slips for this period
            $slips = Slip::where('user_id', $user->id)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->where('status', 'approved')
                ->get()
                ->groupBy('date');

            // Get attendance for the period
            $attendance = Attendance::where('user_id', $user->id)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->get()
                ->keyBy('date');

            // Get performance score aggregates
            $scores = PerformanceScore::where('user_id', $user->id)
                ->whereBetween('period_start', [$dateFrom, $dateTo])
                ->get();
        }

        // Build Growth Plan
        $growthPlan = $this->buildGrowthPlan($user, $metrics, $scores, $monthStr);

        return Inertia::render('Reports/Individual', [
            'employee'     => $user,
            'allEmployees' => $allEmployees,
            'metrics'      => $metrics,
            'slips'        => (object)$slips,
            'attendance'   => (object)$attendance,
            'scores'       => $scores,
            'month'        => $monthStr,
            'days'         => $daysInMonth,
            'period'       => $period,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'metric_id'    => $request->metric_id,
            'growthPlan'   => $growthPlan,
        ]);
    }

    private function resolvePeriod(string $period, string $monthStr): array
    {
        $month = Carbon::parse($monthStr);
        $today = Carbon::today();

        return match($period) {
            '10_days'     => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->startOfMonth()->addDays(9)->toDateString()],
            '20_days'     => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->startOfMonth()->addDays(19)->toDateString()],
            '30_days'     => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->startOfMonth()->addDays(29)->toDateString()],
            '3_months'    => [$today->copy()->subMonths(3)->toDateString(), $today->toDateString()],
            '6_months'    => [$today->copy()->subMonths(6)->toDateString(), $today->toDateString()],
            '1_year'      => [$today->copy()->subYear()->toDateString(), $today->toDateString()],
            default       => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()], // this_month
        };
    }

    public function team(Request $request)
    {
        $month  = $request->month ? Carbon::parse($request->month) : Carbon::today();
        $period = $request->period ?? '30_days'; // default: 30-day consolidated
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd   = $month->copy()->endOfMonth();

        // Pass all active metrics so Vue can generate dynamic columns
        $metrics = Metric::all();

        $users = User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))->get();

        // Get performance score aggregates for the selected period type
        $scores = PerformanceScore::where('period_type', $period)
            ->whereBetween('period_start', [$monthStart, $monthEnd])
            ->get();

        return Inertia::render('Reports/Team', [
            'employees' => $users,
            'metrics'   => $metrics,
            'scores'    => $scores,
            'month'     => $month->format('Y-m'),
            'period'    => $period,
        ]);
    }

    /**
     * Build Growth Plan advice for the employee.
     */
    private function buildGrowthPlan($user, $metrics, $scores, $monthStr): ?array
    {
        if (!$user || empty($metrics) || empty($scores)) {
            return null;
        }

        $month = \Carbon\Carbon::parse($monthStr);
        $dateFrom = $month->copy()->startOfMonth()->toDateString();
        $dateTo = $month->copy()->endOfMonth()->toDateString();

        $totalScore = 0;
        $adviceItems = [];

        // Tier order: green is best, grey is worst (for normal gte metrics)
        $tierOrder = ['green' => 4, 'yellow' => 3, 'red' => 2, 'grey' => 1];
        $tierEmoji = ['green' => '🟢', 'yellow' => '🟡', 'red' => '🔴', 'grey' => '⬜'];
        $tierColors = ['green' => '#28a745', 'yellow' => '#ffc107', 'red' => '#dc3545', 'grey' => '#6c757d'];

        foreach ($metrics as $metric) {
            // Find the 30_days score, fall back to monthly
            $score = $scores->first(fn($s) => $s->metric_id === $metric->id && $s->period_type === '30_days');
            if (!$score) {
                $score = $scores->first(fn($s) => $s->metric_id === $metric->id && $s->period_type === 'monthly');
            }

            $currentValue = $score ? floatval($score->cumulative_value) : 0;
            $earnedPoints = $score ? floatval($score->period_points_earned) : 0;
            $currentLight = $score->traffic_light ?? 'grey';
            $totalScore += $earnedPoints;

            // Get period targets (30_days or monthly) sorted by min_value desc
            $targets = $metric->periodTargets
                ->filter(fn($t) => in_array($t->period_type, ['30_days', 'monthly']))
                ->sortByDesc('min_value')
                ->values();

            if ($targets->isEmpty()) continue;

            $isInverse = $metric->comparison_type === 'lte'; // Late: lower is better
            $isPercentage = $metric->value_type === 'percentage';

            // Determine current tier rank
            $currentTierRank = $tierOrder[$currentLight] ?? 0;

            // Build tier breakdown
            $tiers = [];
            foreach ($targets as $target) {
                $tierLabel = $target->tier_label;
                $tierRank = $tierOrder[$tierLabel] ?? 0;
                $minVal = floatval($target->min_value);
                $pts = floatval($target->points_awarded);

                $tierInfo = [
                    'label' => $tierLabel,
                    'emoji' => $tierEmoji[$tierLabel] ?? '⬜',
                    'color' => $tierColors[$tierLabel] ?? '#6c757d',
                    'threshold' => $minVal,
                    'points' => $pts,
                    'gap' => null,
                    'gapText' => null,
                    'isCurrent' => $tierLabel === $currentLight,
                    'isAchieved' => $tierRank <= $currentTierRank,
                ];

                // Calculate gap to this tier
                if ($isInverse) {
                    // For inverse metrics: achieved if value <= threshold
                    $tierInfo['isAchieved'] = $currentValue <= $minVal;
                    if (!$tierInfo['isAchieved']) {
                        $gap = $currentValue - $minVal;
                        $tierInfo['gap'] = $gap;
                        $tierInfo['gapText'] = 'Reduce by ' . $this->formatValue($gap, $metric) . ' to reach';
                    }
                } else {
                    // For normal metrics: achieved if value >= threshold
                    if (str_contains(strtolower($metric->key), 'collection') || str_contains(strtolower($metric->label), 'collection')) {
                        $tierInfo['isAchieved'] = $currentValue >= $minVal;
                        if (!$tierInfo['isAchieved']) {
                            // Calculate dynamic pending collection amount
                            $refMetricId = $metric->reference_metric_id;
                            $refValue = 0;
                            if ($refMetricId) {
                                $refValue = \App\Models\Slip::where('user_id', $user->id)
                                    ->where('metric_id', $refMetricId)
                                    ->where('status', 'approved')
                                    ->whereBetween('date', [$dateFrom, $dateTo])
                                    ->sum('value') ?? 0;
                            }
                            $currentCollection = \App\Models\Slip::where('user_id', $user->id)
                                ->where('metric_id', $metric->id)
                                ->where('status', 'approved')
                                ->whereBetween('date', [$dateFrom, $dateTo])
                                ->sum('value') ?? 0;

                            $targetAmount = ($minVal / 100) * $refValue;
                            $gapAmount = $targetAmount - $currentCollection;

                            if ($gapAmount > 0) {
                                $tierInfo['gap'] = $gapAmount;
                                $tierInfo['gapText'] = 'Need ' . $this->formatValue($gapAmount, $metric) . ' more to reach';
                            } else {
                                $tierInfo['gap'] = 0;
                                $tierInfo['gapText'] = 'no pending collection to reach';
                            }
                        }
                    } elseif ($isPercentage) {
                        $tierInfo['isAchieved'] = $currentValue >= $minVal;
                        if (!$tierInfo['isAchieved']) {
                            $gap = $minVal - $currentValue;
                            $tierInfo['gap'] = $gap;
                            $tierInfo['gapText'] = 'Need ' . number_format($gap, 1) . '% more to reach';
                        }
                    } else {
                        $tierInfo['isAchieved'] = $currentValue >= $minVal;
                        if (!$tierInfo['isAchieved']) {
                            $gap = $minVal - $currentValue;
                            $tierInfo['gap'] = $gap;
                            $tierInfo['gapText'] = 'Need ' . $this->formatValue($gap, $metric) . ' more to reach';
                        }
                    }
                }

                $tiers[] = $tierInfo;
            }

            // Generate advice text
            $advice = '';
            if ($currentTierRank >= 4 || ($isInverse && $currentLight === 'green')) {
                $advice = '✅ On track! Maintaining Green tier.';
            } elseif ($isInverse) {
                $greenTarget = $targets->firstWhere('tier_label', 'green');
                if ($greenTarget) {
                    $advice = 'Keep at or below ' . $this->formatValue(floatval($greenTarget->min_value), $metric) . ' to maintain Green (' . floatval($greenTarget->points_awarded) . ' pts).';
                }
            } else {
                // Find the next tier up
                $nextTier = collect($tiers)
                    ->filter(fn($t) => !$t['isAchieved'] && $t['gap'] !== null)
                    ->sortBy('threshold')
                    ->first();
                if ($nextTier) {
                    $advice = $nextTier['gapText'] . ' ' . $nextTier['emoji'] . ' ' . ucfirst($nextTier['label']) . ' (' . $nextTier['points'] . ' pts).';
                }
            }

            $adviceItems[] = [
                'metricId' => $metric->id,
                'metricLabel' => $metric->label,
                'metricKey' => $metric->key,
                'unit' => $metric->unit,
                'isInverse' => $isInverse,
                'isPercentage' => $isPercentage,
                'currentValue' => $currentValue,
                'currentValueFormatted' => $this->formatValue($currentValue, $metric),
                'currentTier' => $currentLight,
                'earnedPoints' => $earnedPoints,
                'advice' => $advice,
                'tiers' => $tiers,
            ];
        }

        return [
            'employeeName' => $user->name,
            'month' => $monthStr,
            'totalScore' => round($totalScore, 2),
            'items' => $adviceItems,
        ];
    }

    /**
     * Format a metric value with proper units.
     */
    private function formatValue(float $value, $metric): string
    {
        $unit = strtolower($metric->unit ?? '');

        if (in_array($unit, ['amount', 'rupees', 'inr', '₹'])) {
            if ($value >= 100000) {
                return '₹' . number_format($value / 100000, 2) . 'L';
            } elseif ($value >= 1000) {
                return '₹' . number_format($value / 1000, 1) . 'K';
            }
            return '₹' . number_format($value, 0);
        }

        if (in_array($unit, ['percentage', '%'])) {
            return number_format($value, 1) . '%';
        }

        // For count, ltr, boxes, leaves, time, etc.
        if ($value == intval($value)) {
            return number_format($value, 0) . ' ' . $metric->unit;
        }
        return number_format($value, 1) . ' ' . $metric->unit;
    }
}
