<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Slip;
use App\Models\Metric;
use App\Models\PeriodTarget;
use App\Models\PerformanceScore;
use App\Models\SummaryReport;
use Carbon\Carbon;

class CalculateScores extends Command
{
    protected $signature = 'scores:calculate {--date= : Specific date to calculate for (default: today)}';
    protected $description = 'Nightly engine: calculate performance scores from APPROVED slips only.';

    public function handle()
    {
        $targetDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $this->info("Running score calculation for: {$targetDate->toDateString()}");

        $users   = User::with('roles')->get();
        $metrics = Metric::where('is_active', true)->get();

        foreach ($users as $user) {
            $this->processUserPerformance($user, $metrics, $targetDate);
        }

        $this->info("Score calculation complete.");
    }

    private function processUserPerformance(User $user, $metrics, Carbon $targetDate)
    {
        // Step 1: Get all APPROVED slips for this user in the current month
        $monthStart = $targetDate->copy()->startOfMonth();
        $monthEnd   = $targetDate->copy()->endOfMonth();

        $approvedSlips = Slip::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get()
            ->groupBy('metric_id');

        // Step 2: Determine which period boundaries we've crossed (10, 20, 30 days)
        $dayOfMonth = $targetDate->day;
        $periodsToCalculate = [];
        if ($dayOfMonth >= 10) $periodsToCalculate[] = ['type' => '10_days', 'start' => $monthStart, 'end' => $monthStart->copy()->addDays(9)];
        if ($dayOfMonth >= 20) $periodsToCalculate[] = ['type' => '20_days', 'start' => $monthStart, 'end' => $monthStart->copy()->addDays(19)];
        if ($dayOfMonth >= 30) $periodsToCalculate[] = ['type' => '30_days', 'start' => $monthStart, 'end' => $monthEnd];

        $userRoleId = $user->roles->first()?->id;

        foreach ($metrics as $metric) {
            $slipsForMetric = $approvedSlips->get($metric->id, collect());
            $slipsForRef    = $metric->reference_metric_id ? $approvedSlips->get($metric->reference_metric_id, collect()) : collect();

            $sortOrder = ($metric->comparison_type === 'lte') ? 'asc' : 'desc';
            $operator  = ($metric->comparison_type === 'lte') ? '<=' : '>=';

            if ($metric->scoring_type === 'monthly_flat') {
                // Monthly flat: sum values and compare against monthly period_targets
                $sumValue     = $slipsForMetric->sum('value');
                $sumRef       = $slipsForRef->sum('value');
                $dailyPointsSum = $slipsForMetric->sum('daily_points_earned');

                $cumulativeValue = $sumValue;
                if ($metric->value_type === 'percentage' && $sumRef > 0) {
                    $cumulativeValue = ($sumValue / $sumRef) * 100;
                }

                $target = PeriodTarget::where('metric_id', $metric->id)
                    ->where('period_type', 'monthly')
                    ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                    ->where('min_value', $operator, $cumulativeValue)
                    ->orderBy('min_value', $sortOrder)
                    ->first();

                $periodPoints = $target ? $target->points_awarded : 0;
                $trafficLight = $target ? $target->tier_label : 'grey';

                PerformanceScore::updateOrCreate(
                    ['user_id' => $user->id, 'metric_id' => $metric->id, 'period_type' => 'monthly',
                     'period_start' => $monthStart->toDateString(), 'period_end' => $monthEnd->toDateString()],
                    ['cumulative_value' => $cumulativeValue, 'daily_points_sum' => $dailyPointsSum,
                     'period_points_earned' => $periodPoints, 'traffic_light' => $trafficLight]
                );

            } else {
                // 10/20/30 cumulative: process each period boundary
                foreach ($periodsToCalculate as $period) {
                    $periodSlips    = $slipsForMetric->filter(fn($s) => Carbon::parse($s->date)->between($period['start'], $period['end']));
                    $periodRefSlips = $slipsForRef->filter(fn($s) => Carbon::parse($s->date)->between($period['start'], $period['end']));
                    
                    $sumValue = $periodSlips->sum('value');
                    $sumRef   = $periodRefSlips->sum('value');
                    $dailyPointsSum = $periodSlips->sum('daily_points_earned');

                    $cumulativeValue = $sumValue;
                    if ($metric->value_type === 'percentage' && $sumRef > 0) {
                        $cumulativeValue = ($sumValue / $sumRef) * 100;
                    }

                    $target = PeriodTarget::where('metric_id', $metric->id)
                        ->where('period_type', $period['type'])
                        ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                        ->where('min_value', $operator, $cumulativeValue)
                        ->orderBy('min_value', $sortOrder)
                        ->first();

                    $periodPoints = $target ? $target->points_awarded : 0;
                    $trafficLight = $target ? $target->tier_label : 'grey';

                    PerformanceScore::updateOrCreate(
                        ['user_id' => $user->id, 'metric_id' => $metric->id, 'period_type' => $period['type'],
                         'period_start' => $period['start']->toDateString(), 'period_end' => $period['end']->toDateString()],
                        ['cumulative_value' => $cumulativeValue, 'daily_points_sum' => $dailyPointsSum,
                         'period_points_earned' => $periodPoints, 'traffic_light' => $trafficLight]
                    );
                }
            }
        }

        // Step 3: Build summary report for this month
        $allScores = PerformanceScore::where('user_id', $user->id)
            ->where('period_start', '>=', $monthStart->toDateString())
            ->where('period_end', '<=', $monthEnd->toDateString())
            ->get();

        $totalMark = $allScores->sum('period_points_earned');
        $metricPoints = $allScores->mapWithKeys(fn($s) => ["{$s->metric_id}_{$s->period_type}" => $s->period_points_earned]);

        $overallLight = match(true) {
            $totalMark >= 70 => 'green',
            $totalMark >= 50 => 'yellow',
            $totalMark >= 30 => 'red',
            default          => 'grey',
        };

        SummaryReport::updateOrCreate(
            ['user_id' => $user->id, 'year_month' => $targetDate->format('Y-m')],
            ['metric_points' => $metricPoints, 'total_mark' => $totalMark, 'overall_traffic_light' => $overallLight, 'period_type' => 'monthly']
        );
    }
}
