<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class StockCheckingMetricSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 1. Update the Stock Checking metric type
            $metric = Metric::where('key', 'stock_checking')->first();
            
            if (!$metric) {
                $this->command->error('Metric "stock_checking" not found!');
                return;
            }

            $metric->update([
                'scoring_type' => '10_20_30_days',
                'is_active' => true,
            ]);

            // 2. Get the Production role
            $productionRole = Role::where('name', 'Production')->first();
            
            if ($productionRole) {
                // Ensure metric is linked to the role
                $metric->roles()->syncWithoutDetaching([$productionRole->id]);

                // Clear existing tiers for this metric & role
                DailyScoringTier::where('metric_id', $metric->id)->where('role_id', $productionRole->id)->delete();
                PeriodTarget::where('metric_id', $metric->id)->where('role_id', $productionRole->id)->delete();

                // 3. Seed Daily Scoring Tiers
                // Green: 1 update/day = 0.66 points
                DailyScoringTier::create([
                    'metric_id' => $metric->id,
                    'role_id' => $productionRole->id,
                    'tier_label' => 'green',
                    'min_value' => 1,
                    'daily_points' => 0.66,
                ]);

                // 4. Seed Period Targets
                $periodTargets = [
                    // 10 Days (Approx 2nd Week)
                    ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 2, 'points_awarded' => 6.6],
                    
                    // 20 Days (Approx 3rd Week)
                    ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 3, 'points_awarded' => 13.2],

                    // 30 Days (4th Week / Monthly)
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 4, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 2, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 1, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 0, 'points_awarded' => 0],
                ];

                foreach ($periodTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $metric->id,
                        'role_id' => $productionRole->id,
                    ]));
                }
            }

            DB::commit();
            $this->command->info('Stock Checking metric updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error updating Stock Checking metric: ' . $e->getMessage());
        }
    }
}
