<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RetailOldStockPUMetricSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', 'Retail')->first();
        $metric = Metric::where('key', 'old_stock_pu')->first();

        if (!$role || !$metric) {
            $this->command->error('Retail role or old_stock_pu metric not found.');
            return;
        }

        DB::transaction(function () use ($role, $metric) {
            // Update metric type to cumulative
            $metric->update(['scoring_type' => '10_20_30_days']);

            // Clear old tiers for this specific role and metric to avoid duplicates
            DailyScoringTier::where('metric_id', $metric->id)->where('role_id', $role->id)->delete();
            PeriodTarget::where('metric_id', $metric->id)->where('role_id', $role->id)->delete();

            // Insert Daily Tiers
            $dailyTiers = [
                ['tier_label' => 'green',  'min_value' => 0.20, 'daily_points' => 0.67],
                ['tier_label' => 'yellow', 'min_value' => 0.13, 'daily_points' => 0.46],
                ['tier_label' => 'red',    'min_value' => 0.10, 'daily_points' => 0.33],
                ['tier_label' => 'grey',   'min_value' => 0.06, 'daily_points' => 0.33],
            ];

            foreach ($dailyTiers as $tier) {
                DailyScoringTier::create(array_merge($tier, [
                    'metric_id' => $metric->id,
                    'role_id'   => $role->id,
                ]));
            }

            // Insert Period Tiers
            $periodTiers = [
                // 10 Days
                ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 2.00, 'points_awarded' => 6.67],
                ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 1.30, 'points_awarded' => 4.60],
                ['period_type' => '10_days', 'tier_label' => 'red',    'min_value' => 1.00, 'points_awarded' => 3.30],
                ['period_type' => '10_days', 'tier_label' => 'grey',   'min_value' => 0.60, 'points_awarded' => 3.30],

                // 20 Days
                ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 4.00, 'points_awarded' => 13.33],
                ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 2.60, 'points_awarded' => 9.20],
                ['period_type' => '20_days', 'tier_label' => 'red',    'min_value' => 2.00, 'points_awarded' => 6.60],
                ['period_type' => '20_days', 'tier_label' => 'grey',   'min_value' => 1.20, 'points_awarded' => 6.60],

                // 30 Days
                ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 6.00, 'points_awarded' => 20.00],
                ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 3.90, 'points_awarded' => 14.00],
                ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 3.00, 'points_awarded' => 10.00],
                ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 1.80, 'points_awarded' => 6.00],
            ];

            foreach ($periodTiers as $tier) {
                PeriodTarget::create(array_merge($tier, [
                    'metric_id' => $metric->id,
                    'role_id'   => $role->id,
                ]));
            }
        });

        $this->command->info('Old Stock Clearing PU Paint scoring tiers seeded successfully for Retail role.');
    }
}
