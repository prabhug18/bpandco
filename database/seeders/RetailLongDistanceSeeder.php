<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\AppSetting;

class RetailLongDistanceSeeder extends Seeder
{
    /**
     * Create 'Retail Long Distance' role by cloning Retail's
     * metric assignments, scoring tiers, period targets,
     * and setting attendance times from the DOCX document.
     */
    public function run(): void
    {
        $retailRole = Role::where('name', 'Retail')->first();

        if (!$retailRole) {
            $this->command->error('Retail role not found. Aborting.');
            return;
        }

        // Step 1: Create role (skip if exists)
        $newRole = Role::firstOrCreate(
            ['name' => 'Retail Long Distance', 'guard_name' => 'web']
        );

        $this->command->info("Step 1: Role 'Retail Long Distance' ready (ID: {$newRole->id})");

        DB::transaction(function () use ($retailRole, $newRole) {

            // Step 2: Copy metric assignments
            $existingMetrics = DB::table('metric_role')
                ->where('role_id', $newRole->id)
                ->pluck('metric_id')
                ->toArray();

            $retailMetrics = DB::table('metric_role')
                ->where('role_id', $retailRole->id)
                ->pluck('metric_id');

            foreach ($retailMetrics as $metricId) {
                if (!in_array($metricId, $existingMetrics)) {
                    DB::table('metric_role')->insert([
                        'metric_id' => $metricId,
                        'role_id'   => $newRole->id,
                    ]);
                }
            }

            $this->command->info("Step 2: Copied " . $retailMetrics->count() . " metric assignments");

            // Step 3: Copy daily scoring tiers
            $existingTiers = DB::table('daily_scoring_tiers')
                ->where('role_id', $newRole->id)
                ->count();

            if ($existingTiers === 0) {
                $retailTiers = DB::table('daily_scoring_tiers')
                    ->where('role_id', $retailRole->id)
                    ->get();

                foreach ($retailTiers as $tier) {
                    DB::table('daily_scoring_tiers')->insert([
                        'metric_id'    => $tier->metric_id,
                        'role_id'      => $newRole->id,
                        'min_value'    => $tier->min_value,
                        'daily_points' => $tier->daily_points,
                        'tier_label'   => $tier->tier_label,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }

                $this->command->info("Step 3: Copied {$retailTiers->count()} daily scoring tiers");
            } else {
                $this->command->warn("Step 3: Skipped — {$existingTiers} daily tiers already exist");
            }

            // Step 4: Copy period targets
            $existingTargets = DB::table('period_targets')
                ->where('role_id', $newRole->id)
                ->count();

            if ($existingTargets === 0) {
                $retailTargets = DB::table('period_targets')
                    ->where('role_id', $retailRole->id)
                    ->get();

                foreach ($retailTargets as $target) {
                    DB::table('period_targets')->insert([
                        'metric_id'      => $target->metric_id,
                        'role_id'        => $newRole->id,
                        'period_type'    => $target->period_type,
                        'min_value'      => $target->min_value,
                        'points_awarded' => $target->points_awarded,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }

                $this->command->info("Step 4: Copied {$retailTargets->count()} period targets");
            } else {
                $this->command->warn("Step 4: Skipped — {$existingTargets} period targets already exist");
            }

            // Step 5: Set AppSettings from DOCX document
            // RETAIL LONG DISTANCE – 9.30AM
            $settings = [
                'attendance_work_start' => '09:30',
                'attendance_grace_end'  => '09:45',
                'attendance_half_day'   => '10:30',
            ];

            foreach ($settings as $key => $value) {
                AppSetting::updateOrCreate(
                    ['role_id' => $newRole->id, 'key' => $key],
                    ['value' => $value]
                );
            }

            $this->command->info("Step 5: AppSettings configured (Work Start: 09:30, Grace: 09:45, Half-Day: 10:30)");
        });

        $this->command->info("✅ 'Retail Long Distance' role setup complete!");
    }
}
