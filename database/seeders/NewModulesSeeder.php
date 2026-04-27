<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metric;
use App\Models\PeriodTarget;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class NewModulesSeeder extends Seeder
{
    public function run()
    {
        // 1. Define the new metrics
        $newMetrics = [
            [
                'key' => 'old_stock_enamel',
                'label' => 'Old Stock Clearing Enamel / Ancillaries',
                'unit' => 'LTR',
                'scoring_type' => 'monthly_flat',
                'tiers' => [
                    ['tier' => 'green',  'min' => 40, 'points' => 20],
                    ['tier' => 'yellow', 'min' => 36, 'points' => 15],
                    ['tier' => 'red',    'min' => 32, 'points' => 10],
                    ['tier' => 'grey',   'min' => 28, 'points' => 5],
                ]
            ],
            [
                'key' => 'old_stock_pu',
                'label' => 'Old Stock Clearing PU Paint / Clear',
                'unit' => 'LTR',
                'scoring_type' => 'monthly_flat',
                'tiers' => [
                    ['tier' => 'green',  'min' => 6, 'points' => 20],
                    ['tier' => 'yellow', 'min' => 4, 'points' => 15],
                    ['tier' => 'red',    'min' => 3, 'points' => 10],
                    ['tier' => 'grey',   'min' => 2, 'points' => 5],
                ]
            ],
            [
                'key' => 'stock_checking',
                'label' => 'Stock Checking (Maruti & Honda)',
                'unit' => 'Updates',
                'scoring_type' => 'monthly_flat',
                'tiers' => [
                    ['tier' => 'green',  'min' => 8, 'points' => 20],
                    ['tier' => 'yellow', 'min' => 6, 'points' => 15],
                    ['tier' => 'red',    'min' => 4, 'points' => 10],
                    ['tier' => 'grey',   'min' => 2, 'points' => 5],
                ]
            ],
        ];

        $userRole = Role::where('name', 'user')->first();

        foreach ($newMetrics as $m) {
            // Create or update the metric
            $metric = Metric::updateOrCreate(
                ['key' => $m['key']],
                [
                    'label' => $m['label'],
                    'unit' => $m['unit'],
                    'scoring_type' => $m['scoring_type'],
                    'is_active' => true,
                ]
            );

            // Create tiers (Period Targets)
            foreach ($m['tiers'] as $t) {
                PeriodTarget::updateOrCreate(
                    [
                        'metric_id' => $metric->id,
                        'period_type' => 'monthly',
                        'tier_label' => $t['tier']
                    ],
                    [
                        'min_value' => $t['min'],
                        'points_awarded' => $t['points'],
                        'updated_by' => 1 // Default to first admin
                    ]
                );
            }

            // Assign to user role if not already assigned
            if ($userRole && !$metric->roles()->where('role_id', $userRole->id)->exists()) {
                $metric->roles()->attach($userRole->id);
            }
        }

        // Also ensure Attendance (ID 5) is assigned to 'user' role as requested
        $attendance = Metric::where('key', 'attendance')->first();
        if ($attendance && $userRole && !$attendance->roles()->where('role_id', $userRole->id)->exists()) {
            $attendance->roles()->attach($userRole->id);
        }
    }
}
