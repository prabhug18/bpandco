<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MetricTargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metrics = [
            [
                'key' => 'sales',
                'label' => 'Sales',
                'unit' => 'amount',
                'scoring_type' => '10_20_30_days',
            ],
            [
                'key' => 'collection',
                'label' => 'Collection',
                'unit' => 'amount',
                'scoring_type' => 'monthly_flat', // Per the slide targets vs excel targets config dynamic option
            ],
            [
                'key' => 'colour_matching',
                'label' => 'Colour Matching',
                'unit' => 'count',
                'scoring_type' => '10_20_30_days',
            ],
            [
                'key' => 'customer_handling',
                'label' => 'Customer Handling',
                'unit' => 'count',
                'scoring_type' => '10_20_30_days',
            ],
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'unit' => 'leaves',
                'scoring_type' => 'monthly_flat',
            ],
            [
                'key' => 'duty_time',
                'label' => 'Duty Time',
                'unit' => 'time',
                'scoring_type' => '10_20_30_days', // Or flat monthly? Excel implies per day points. Let's keep 10/20/30.
            ],
            [
                'key' => 'late',
                'label' => 'Late',
                'unit' => 'count',
                'scoring_type' => 'monthly_flat', // Per slides, late is 4/month = 20 pts...
            ],
            [
                'key' => 'panel_collection',
                'label' => 'Panel Collection',
                'unit' => 'count',
                'scoring_type' => 'monthly_flat',
            ]
        ];

        foreach ($metrics as $metric) {
            \App\Models\Metric::firstOrCreate(
                ['key' => $metric['key']],
                [
                    'label' => $metric['label'],
                    'unit' => $metric['unit'],
                    'scoring_type' => $metric['scoring_type'],
                    'is_active' => true
                ]
            );
        }

        // Assign metrics to roles
        $admin = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        $supervisor = \Spatie\Permission\Models\Role::where('name', 'supervisor')->first();
        $user = \Spatie\Permission\Models\Role::where('name', 'user')->first();

        $metricsToAssign = \App\Models\Metric::all();
        $userMetricKeys = ['sales', 'collection', 'colour_matching', 'customer_handling', 'duty_time'];

        foreach ($metricsToAssign as $m) {
            $rolesToSync = [];
            if ($admin) $rolesToSync[] = $admin->id;
            if ($supervisor) $rolesToSync[] = $supervisor->id;

            if ($user && in_array($m->key, $userMetricKeys)) {
                $rolesToSync[] = $user->id;
            }

            $m->roles()->sync($rolesToSync);
        }

        $this->command->info('Base metrics seeded and attached securely!');
    }
}
