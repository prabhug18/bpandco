<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;

class RetailEnamelOldStockEnamelSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('name', 'retail enamel')->first();
        $metric = Metric::where('key', 'old_stock_enamel')->first();

        if (!$role || !$metric) {
            $this->command->error("Role 'retail enamel' or Metric 'old_stock_enamel' not found!");
            return;
        }

        // 1. Update Metric Configuration to 10/20/30 day logic
        $metric->update([
            'scoring_type' => '10_20_30_days',
            'unit' => 'ltrs'
        ]);

        // Clear existing tiers for this role/metric to avoid duplicates
        DailyScoringTier::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();
        PeriodTarget::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();

        // 2. Daily Scoring Tiers
        $dailyTiers = [
            ['tier_label' => 'green', 'min_value' => 1.33, 'daily_points' => 0.67],
            ['tier_label' => 'yellow', 'min_value' => 1.20, 'daily_points' => 0.50],
            ['tier_label' => 'red', 'min_value' => 1.07, 'daily_points' => 0.37],
            ['tier_label' => 'grey', 'min_value' => 0.93, 'daily_points' => 0.23],
        ];

        foreach ($dailyTiers as $tier) {
            DailyScoringTier::create(array_merge($tier, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1
            ]));
        }

        // 3. Period Targets
        $periodTargets = [
            // 10 Days
            ['period_type' => '10_days', 'tier_label' => 'green', 'min_value' => 13.33, 'points_awarded' => 6.67],
            ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 12.00, 'points_awarded' => 5.00],
            ['period_type' => '10_days', 'tier_label' => 'red', 'min_value' => 10.67, 'points_awarded' => 3.67],
            ['period_type' => '10_days', 'tier_label' => 'grey', 'min_value' => 9.33, 'points_awarded' => 2.33],
            
            // 20 Days
            ['period_type' => '20_days', 'tier_label' => 'green', 'min_value' => 26.67, 'points_awarded' => 13.33],
            ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 24.00, 'points_awarded' => 10.00],
            ['period_type' => '20_days', 'tier_label' => 'red', 'min_value' => 21.33, 'points_awarded' => 7.33],
            ['period_type' => '20_days', 'tier_label' => 'grey', 'min_value' => 18.67, 'points_awarded' => 4.67],
            
            // 30 Days (Monthly)
            ['period_type' => 'monthly', 'tier_label' => 'green', 'min_value' => 40.00, 'points_awarded' => 20],
            ['period_type' => 'monthly', 'tier_label' => 'yellow', 'min_value' => 36.00, 'points_awarded' => 15],
            ['period_type' => 'monthly', 'tier_label' => 'red', 'min_value' => 32.00, 'points_awarded' => 11],
            ['period_type' => 'monthly', 'tier_label' => 'grey', 'min_value' => 28.00, 'points_awarded' => 7],
        ];

        foreach ($periodTargets as $target) {
            PeriodTarget::create(array_merge($target, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1
            ]));
        }

        $this->command->info("Scoring tiers and targets for 'Retail Enamel' - 'Old Stock Clearing Enamel / Ancillaries' implemented successfully!");
    }
}
