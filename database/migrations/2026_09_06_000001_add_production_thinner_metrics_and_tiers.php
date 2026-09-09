<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create or get new Thinner metrics
        $ncMetric = Metric::firstOrCreate(
            ['key' => 'nc_thinner_mixing'],
            [
                'label'           => 'NC Thinner Mixing',
                'unit'            => 'Ltr',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        $enamelMetric = Metric::firstOrCreate(
            ['key' => 'enamel_thinner_mixing'],
            [
                'label'           => 'Enamel Thinner Mixing',
                'unit'            => 'Ltr',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        $mixingMetric = Metric::firstOrCreate(
            ['key' => 'mixing_thinners'],
            [
                'label'           => 'Mixing Thinners',
                'unit'            => 'Ltr',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        // Ensure Box production metric exists and has proper label
        $boxMetric = Metric::firstOrCreate(
            ['key' => 'production'],
            [
                'label'           => 'Box Production',
                'unit'            => 'Boxes',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );
        $boxMetric->update(['label' => 'Box Production', 'unit' => 'Boxes']);

        $stockMetric = Metric::firstOrCreate(
            ['key' => 'stock_checking'],
            [
                'label'           => 'Stock Checking (Maruti & Honda)',
                'unit'            => 'Updates',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        $attMetric  = Metric::where('key', 'attendance')->first();
        $lateMetric = Metric::where('key', 'late')->first();

        // 2. Link metrics to the Production role
        $productionRole = Role::where('name', 'Production')->first();

        if ($productionRole) {
            $roleId = $productionRole->id;

            $productionRole->givePermissionTo([
                'submit slips',
            ]);

            // Sync metric role mappings
            $metricIdsToAssign = array_filter([
                $boxMetric->id,
                $ncMetric->id,
                $enamelMetric->id,
                $mixingMetric->id,
                $stockMetric->id,
                $attMetric?->id,
                $lateMetric?->id,
            ]);

            foreach ($metricIdsToAssign as $mId) {
                \Illuminate\Support\Facades\DB::table('metric_role')->updateOrInsert(
                    ['metric_id' => $mId, 'role_id' => $roleId]
                );
            }

            // Remove unrelated 'bills' or 'sales' if previously assigned by mistake to Production role
            $billsMetric = Metric::where('key', 'bills')->first();
            if ($billsMetric) {
                \Illuminate\Support\Facades\DB::table('metric_role')
                    ->where('metric_id', $billsMetric->id)
                    ->where('role_id', $roleId)
                    ->delete();
            }

            // 3. Populate Daily Scoring Tiers for Production Role ONLY IF THEY DON'T EXIST
            // Box Production Daily Tiers (Sheet: Green 15 => 0.66, Yellow 12 => 0.46, Red 10 => 0.33, Grey 7 => 0.20)
            if (DailyScoringTier::where('metric_id', $boxMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $boxDailyTiers = [
                    ['tier_label' => 'green',  'min_value' => 15, 'daily_points' => 0.66],
                    ['tier_label' => 'yellow', 'min_value' => 12, 'daily_points' => 0.46],
                    ['tier_label' => 'red',    'min_value' => 10, 'daily_points' => 0.33],
                    ['tier_label' => 'grey',   'min_value' => 7,  'daily_points' => 0.20],
                ];
                foreach ($boxDailyTiers as $t) {
                    DailyScoringTier::create(array_merge($t, [
                        'metric_id' => $boxMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }

            // Mixing Thinners Daily Tiers (Sheet: Green 300 => 0.66, Yellow 220 => 0.46, Red 180 => 0.33, Grey 140 => 0.20)
            if (DailyScoringTier::where('metric_id', $mixingMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $mixingDailyTiers = [
                    ['tier_label' => 'green',  'min_value' => 300, 'daily_points' => 0.66],
                    ['tier_label' => 'yellow', 'min_value' => 220, 'daily_points' => 0.46],
                    ['tier_label' => 'red',    'min_value' => 180, 'daily_points' => 0.33],
                    ['tier_label' => 'grey',   'min_value' => 140, 'daily_points' => 0.20],
                ];
                foreach ($mixingDailyTiers as $t) {
                    DailyScoringTier::create(array_merge($t, [
                        'metric_id' => $mixingMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }

            // Stock Checking Daily Tiers (Sheet: 1st Week: 1 => 0.66)
            if (DailyScoringTier::where('metric_id', $stockMetric->id)->where('role_id', $roleId)->doesntExist()) {
                DailyScoringTier::create([
                    'tier_label'   => 'green',
                    'min_value'    => 1,
                    'daily_points' => 0.66,
                    'metric_id'    => $stockMetric->id,
                    'role_id'      => $roleId,
                ]);
            }

            // 4. Populate Period Targets for Production Role ONLY IF THEY DON'T EXIST
            // Box Production Period Targets
            if (PeriodTarget::where('metric_id', $boxMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $boxPeriodTargets = [
                    // 10 Days
                    ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 150, 'points_awarded' => 6.6],
                    ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 120, 'points_awarded' => 4.6],
                    ['period_type' => '10_days', 'tier_label' => 'red',    'min_value' => 100, 'points_awarded' => 3.3],
                    ['period_type' => '10_days', 'tier_label' => 'grey',   'min_value' => 70,  'points_awarded' => 2.0],
                    // 20 Days
                    ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 300, 'points_awarded' => 13.2],
                    ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 240, 'points_awarded' => 9.2],
                    ['period_type' => '20_days', 'tier_label' => 'red',    'min_value' => 200, 'points_awarded' => 6.6],
                    ['period_type' => '20_days', 'tier_label' => 'grey',   'min_value' => 140, 'points_awarded' => 4.0],
                    // 30 Days (Max 20 pts)
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 450, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 360, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 300, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 210, 'points_awarded' => 6],
                ];
                foreach ($boxPeriodTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $boxMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }

            // Mixing Thinners Period Targets
            if (PeriodTarget::where('metric_id', $mixingMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $mixingPeriodTargets = [
                    // 10 Days
                    ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 3000, 'points_awarded' => 6.6],
                    ['period_type' => '10_days', 'tier_label' => 'yellow', 'min_value' => 2200, 'points_awarded' => 4.6],
                    ['period_type' => '10_days', 'tier_label' => 'red',    'min_value' => 1800, 'points_awarded' => 3.3],
                    ['period_type' => '10_days', 'tier_label' => 'grey',   'min_value' => 1400, 'points_awarded' => 2.0],
                    // 20 Days
                    ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 6000, 'points_awarded' => 13.2],
                    ['period_type' => '20_days', 'tier_label' => 'yellow', 'min_value' => 4400, 'points_awarded' => 9.2],
                    ['period_type' => '20_days', 'tier_label' => 'red',    'min_value' => 3600, 'points_awarded' => 6.6],
                    ['period_type' => '20_days', 'tier_label' => 'grey',   'min_value' => 2800, 'points_awarded' => 4.0],
                    // 30 Days (Max 20 pts)
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 9000, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 6600, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 5400, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 4200, 'points_awarded' => 6],
                ];
                foreach ($mixingPeriodTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $mixingMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }

            // Stock Checking Period Targets
            if (PeriodTarget::where('metric_id', $stockMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $stockPeriodTargets = [
                    ['period_type' => '10_days', 'tier_label' => 'green',  'min_value' => 2, 'points_awarded' => 6.6],
                    ['period_type' => '20_days', 'tier_label' => 'green',  'min_value' => 3, 'points_awarded' => 13.2],
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 4, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 2, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 1, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 0, 'points_awarded' => 0],
                ];
                foreach ($stockPeriodTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $stockMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }

            // Attendance Period Targets (Max 20 pts)
            if ($attMetric && PeriodTarget::where('metric_id', $attMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $attTargets = [
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 25, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 24, 'points_awarded' => 14],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 23, 'points_awarded' => 10],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 22, 'points_awarded' => 6],
                ];
                foreach ($attTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $attMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }

            // In Time (Late) Period Targets (Max 20 pts)
            if ($lateMetric && PeriodTarget::where('metric_id', $lateMetric->id)->where('role_id', $roleId)->doesntExist()) {
                $lateTargets = [
                    ['period_type' => '30_days', 'tier_label' => 'green',  'min_value' => 4, 'points_awarded' => 20],
                    ['period_type' => '30_days', 'tier_label' => 'yellow', 'min_value' => 5, 'points_awarded' => 13],
                    ['period_type' => '30_days', 'tier_label' => 'red',    'min_value' => 6, 'points_awarded' => 9],
                    ['period_type' => '30_days', 'tier_label' => 'grey',   'min_value' => 7, 'points_awarded' => 5],
                ];
                foreach ($lateTargets as $target) {
                    PeriodTarget::create(array_merge($target, [
                        'metric_id' => $lateMetric->id,
                        'role_id'   => $roleId,
                    ]));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe reversible logic
    }
};
