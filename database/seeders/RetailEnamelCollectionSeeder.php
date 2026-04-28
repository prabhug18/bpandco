<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;

class RetailEnamelCollectionSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('name', 'retail enamel')->first();
        $salesMetric = Metric::where('key', 'sales')->first();
        $collectionMetric = Metric::where('key', 'collection')->first();

        if (!$role || !$salesMetric || !$collectionMetric) {
            $this->command->error("Role 'retail enamel', Sales Metric, or Collection Metric not found!");
            return;
        }

        // 1. Update Collection Metric Configuration
        $collectionMetric->update([
            'value_type' => 'percentage',
            'reference_metric_id' => $salesMetric->id,
            'scoring_type' => '10_20_30_days', // Changed from monthly_flat per requirements
            'unit' => 'percentage'
        ]);

        // Clear existing tiers for this role/metric to avoid duplicates
        DailyScoringTier::where('role_id', $role->id)->where('metric_id', $collectionMetric->id)->delete();
        PeriodTarget::where('role_id', $role->id)->where('metric_id', $collectionMetric->id)->delete();

        // 2. Daily Scoring Tiers (Percentage based)
        $dailyTiers = [
            ['tier_label' => 'green', 'min_value' => 110, 'daily_points' => 0.67],
            ['tier_label' => 'yellow', 'min_value' => 80, 'daily_points' => 0.47],
            ['tier_label' => 'red', 'min_value' => 70, 'daily_points' => 0.33],
            ['tier_label' => 'grey', 'min_value' => 60, 'daily_points' => 0.17],
        ];

        foreach ($dailyTiers as $tier) {
            DailyScoringTier::create(array_merge($tier, [
                'role_id' => $role->id,
                'metric_id' => $collectionMetric->id,
                'updated_by' => 1
            ]));
        }

        // 3. Period Targets (Percentage based)
        $periodTargets = [
            // 10 Days
            ['period_type' => '10_days', 'tier_label' => 'green', 'min_value' => 110, 'points_awarded' => 6.67],
            ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 80, 'points_awarded' => 4.67],
            ['period_type' => '10_days', 'tier_label' => 'red', 'min_value' => 70, 'points_awarded' => 3.33],
            ['period_type' => '10_days', 'tier_label' => 'grey', 'min_value' => 60, 'points_awarded' => 1.67],
            
            // 20 Days
            ['period_type' => '20_days', 'tier_label' => 'green', 'min_value' => 110, 'points_awarded' => 13.33],
            ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 80, 'points_awarded' => 9.33],
            ['period_type' => '20_days', 'tier_label' => 'red', 'min_value' => 70, 'points_awarded' => 6.67],
            ['period_type' => '20_days', 'tier_label' => 'grey', 'min_value' => 60, 'points_awarded' => 3.33],
            
            // 30 Days (Monthly)
            ['period_type' => 'monthly', 'tier_label' => 'green', 'min_value' => 110, 'points_awarded' => 20],
            ['period_type' => 'monthly', 'tier_label' => 'yellow', 'min_value' => 80, 'points_awarded' => 14],
            ['period_type' => 'monthly', 'tier_label' => 'red', 'min_value' => 70, 'points_awarded' => 10],
            ['period_type' => 'monthly', 'tier_label' => 'grey', 'min_value' => 60, 'points_awarded' => 5],
        ];

        foreach ($periodTargets as $target) {
            PeriodTarget::create(array_merge($target, [
                'role_id' => $role->id,
                'metric_id' => $collectionMetric->id,
                'updated_by' => 1
            ]));
        }

        $this->command->info("Scoring tiers and targets for 'Retail Enamel' - 'Collection' (Percentage based) implemented successfully!");
    }
}
