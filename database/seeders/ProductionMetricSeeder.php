<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class ProductionMetricSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 1. Get or create the Production metric
            $metric = Metric::firstOrCreate(
                ['key' => 'production'],
                [
                    'label' => 'Production',
                    'unit' => 'Boxes',
                    'value_type' => 'absolute',
                    'scoring_type' => '10_20_30_days',
                    'comparison_type' => 'gte',
                    'is_active' => true,
                ]
            );

            // 2. Link metric to the Production role (Role ID: 7 based on earlier query)
            $productionRole = Role::where('name', 'Production')->first();
            
            if ($productionRole) {
                $metric->roles()->syncWithoutDetaching([$productionRole->id]);
                
                // Clear existing tiers for this metric & role to avoid duplicates
                DailyScoringTier::where('metric_id', $metric->id)->where('role_id', $productionRole->id)->delete();
                PeriodTarget::where('metric_id', $metric->id)->where('role_id', $productionRole->id)->delete();

                // 3. Seed Daily Scoring Tiers
                $dailyTiers = [
                    ['tier_label' => 'green',  'min_value' => 80, 'daily_points' => 0.66],
                    ['tier_label' => 'yellow', 'min_value' => 60, 'daily_points' => 0.46],
                    ['tier_label' => 'red',    'min_value' => 50, 'daily_points' => 0.33],
                    ['tier_label' => 'grey',   'min_value' => 40, 'daily_points' => 0.20],
                ];

                foreach ($dailyTiers as $tier) {
                    DailyScoringTier::create(array_merge($tier, [
                        'metric_id' => $metric->id,
                        'role_id' => $productionRole->id,
                    ]));
                }

                // 4. Seed Period Targets
                $periodTargets = [
                    // 10 Days
                    ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 800, 'points_awarded' => 6.6],
                    ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 600, 'points_awarded' => 4.6],
                    ['period_type' => '10_days', 'tier_label' => 'red',    'min_value' => 500, 'points_awarded' => 3.3],
                    ['period_type' => '10_days', 'tier_label' => 'grey',   'min_value' => 400, 'points_awarded' => 2],
                    
                    // 20 Days
                    ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 1600, 'points_awarded' => 13.2],
                    ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 1200, 'points_awarded' => 9.2],
                    ['period_type' => '20_days', 'tier_label' => 'red',    'min_value' => 1000, 'points_awarded' => 6.6],
                    ['period_type' => '20_days', 'tier_label' => 'grey',   'min_value' => 800,  'points_awarded' => 4],

                    // 30 Days
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 2400, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 1800, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 1500, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 1200, 'points_awarded' => 6],
                ];

                foreach ($periodTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $metric->id,
                        'role_id' => $productionRole->id,
                    ]));
                }
            }

            DB::commit();
            $this->command->info('Production metric and scoring tiers seeded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding Production metric: ' . $e->getMessage());
        }
    }
}
