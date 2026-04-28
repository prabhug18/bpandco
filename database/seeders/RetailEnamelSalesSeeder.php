<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;

class RetailEnamelSalesSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('name', 'retail enamel')->first();
        $metric = Metric::where('key', 'sales')->first();

        if (!$role || !$metric) {
            $this->command->error("Role 'retail enamel' or Metric 'sales' not found!");
            return;
        }

        // Clear existing tiers for this role/metric to avoid duplicates
        DailyScoringTier::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();
        PeriodTarget::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();

        // 1. Daily Scoring Tiers
        $dailyTiers = [
            ['tier_label' => 'green', 'min_value' => 15000, 'daily_points' => 0.5],
            ['tier_label' => 'yellow', 'min_value' => 10500, 'daily_points' => 0.33],
            ['tier_label' => 'red', 'min_value' => 7400, 'daily_points' => 0.23],
            ['tier_label' => 'grey', 'min_value' => 4400, 'daily_points' => 0.13],
        ];

        foreach ($dailyTiers as $tier) {
            DailyScoringTier::create(array_merge($tier, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1 // Assuming admin user ID 1
            ]));
        }

        // 2. Period Targets
        $periodTargets = [
            // 10 Days
            ['period_type' => '10_days', 'tier_label' => 'green', 'min_value' => 150000, 'points_awarded' => 5],
            ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 105000, 'points_awarded' => 3.3],
            ['period_type' => '10_days', 'tier_label' => 'red', 'min_value' => 74000, 'points_awarded' => 2.3],
            ['period_type' => '10_days', 'tier_label' => 'grey', 'min_value' => 44000, 'points_awarded' => 1.3],
            
            // 20 Days
            ['period_type' => '20_days', 'tier_label' => 'green', 'min_value' => 300000, 'points_awarded' => 10],
            ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 210000, 'points_awarded' => 6.6],
            ['period_type' => '20_days', 'tier_label' => 'red', 'min_value' => 148000, 'points_awarded' => 4.6],
            ['period_type' => '20_days', 'tier_label' => 'grey', 'min_value' => 88000, 'points_awarded' => 2.6],
            
            // 30 Days (Monthly)
            ['period_type' => 'monthly', 'tier_label' => 'green', 'min_value' => 450000, 'points_awarded' => 15],
            ['period_type' => 'monthly', 'tier_label' => 'yellow', 'min_value' => 315000, 'points_awarded' => 10],
            ['period_type' => 'monthly', 'tier_label' => 'red', 'min_value' => 222000, 'points_awarded' => 7],
            ['period_type' => 'monthly', 'tier_label' => 'grey', 'min_value' => 132000, 'points_awarded' => 4],
        ];

        foreach ($periodTargets as $target) {
            PeriodTarget::create(array_merge($target, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1 // Assuming admin user ID 1
            ]));
        }

        $this->command->info("Scoring tiers and targets for 'Retail Enamel' - 'Sales' implemented successfully!");
    }
}
