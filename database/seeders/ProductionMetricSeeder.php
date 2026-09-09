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
            // 1. Box production metric
            $metric = Metric::firstOrCreate(
                ['key' => 'production'],
                [
                    'label' => 'Box Production',
                    'unit' => 'Boxes',
                    'value_type' => 'absolute',
                    'scoring_type' => '10_20_30_days',
                    'comparison_type' => 'gte',
                    'is_active' => true,
                ]
            );

            // 2. Link metric to the Production role
            $productionRole = Role::where('name', 'Production')->first();
            
            if ($productionRole) {
                $metric->roles()->syncWithoutDetaching([$productionRole->id]);
                
                // Clear existing tiers for this metric & role to avoid duplicates
                DailyScoringTier::where('metric_id', $metric->id)->where('role_id', $productionRole->id)->delete();
                PeriodTarget::where('metric_id', $metric->id)->where('role_id', $productionRole->id)->delete();

                // 3. Seed Daily Scoring Tiers
                $dailyTiers = [
                    ['tier_label' => 'green',  'min_value' => 15, 'daily_points' => 0.66],
                    ['tier_label' => 'yellow', 'min_value' => 12, 'daily_points' => 0.46],
                    ['tier_label' => 'red',    'min_value' => 10, 'daily_points' => 0.33],
                    ['tier_label' => 'grey',   'min_value' => 7,  'daily_points' => 0.20],
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
                    ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 150, 'points_awarded' => 6.6],
                    ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 120, 'points_awarded' => 4.6],
                    ['period_type' => '10_days', 'tier_label' => 'red',    'min_value' => 100, 'points_awarded' => 3.3],
                    ['period_type' => '10_days', 'tier_label' => 'grey',   'min_value' => 70,  'points_awarded' => 2],
                    
                    // 20 Days
                    ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 300, 'points_awarded' => 13.2],
                    ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 240, 'points_awarded' => 9.2],
                    ['period_type' => '20_days', 'tier_label' => 'red',    'min_value' => 200, 'points_awarded' => 6.6],
                    ['period_type' => '20_days', 'tier_label' => 'grey',   'min_value' => 140, 'points_awarded' => 4],

                    // 30 Days
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 450, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 360, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 300, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 210, 'points_awarded' => 6],
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
