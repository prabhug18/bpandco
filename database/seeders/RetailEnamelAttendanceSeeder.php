<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;

class RetailEnamelAttendanceSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('name', 'retail enamel')->first();
        $metric = Metric::where('key', 'attendance')->first();

        if (!$role || !$metric) {
            $this->command->error("Role 'retail enamel' or Metric 'attendance' not found!");
            return;
        }

        // 1. Update Attendance Metric Configuration
        $metric->update([
            'scoring_type' => 'monthly_flat',
            'unit' => 'days'
        ]);

        // Clear existing tiers for this role/metric to avoid duplicates
        DailyScoringTier::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();
        PeriodTarget::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();

        // 2. Daily Scoring Tiers (Fixed points for attendance presence)
        // Present (1.0) -> 1.0 point, Half Day (0.5) -> 0.5 point
        $dailyTiers = [
            ['tier_label' => 'green', 'min_value' => 1.0, 'daily_points' => 1.0],
            ['tier_label' => 'yellow', 'min_value' => 0.5, 'daily_points' => 0.5],
        ];

        foreach ($dailyTiers as $tier) {
            DailyScoringTier::create(array_merge($tier, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1
            ]));
        }

        // 3. Monthly Targets (Based on Leaves)
        // We calculate based on a 26-working-day month assumption for the numeric thresholds
        // 25 days present (1 leave) -> 15 pts
        // 24 days present (2 leaves) -> 10 pts
        // 23 days present (3 leaves) -> 7 pts
        // 22 days present (4 leaves) -> 4 pts
        $monthlyTargets = [
            ['period_type' => 'monthly', 'tier_label' => 'green', 'min_value' => 25, 'points_awarded' => 15],
            ['period_type' => 'monthly', 'tier_label' => 'yellow', 'min_value' => 24, 'points_awarded' => 10],
            ['period_type' => 'monthly', 'tier_label' => 'red', 'min_value' => 23, 'points_awarded' => 7],
            ['period_type' => 'monthly', 'tier_label' => 'grey', 'min_value' => 22, 'points_awarded' => 4],
        ];

        foreach ($monthlyTargets as $target) {
            PeriodTarget::create(array_merge($target, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1
            ]));
        }

        $this->command->info("Scoring tiers and targets for 'Retail Enamel' - 'Attendance' (Monthly Flat) implemented successfully!");
    }
}
