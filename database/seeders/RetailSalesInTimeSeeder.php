<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;

class RetailSalesInTimeSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('name', 'Retail Sales')->first();
        $metric = Metric::where('key', 'late')->first();

        if (!$role || !$metric) {
            $this->command->error("Role 'Retail Sales' or Metric 'late' not found!");
            return;
        }

        // 1. Update Metric Configuration
        // Note: setting comparison_type to 'lte' (Lower is Better)
        $metric->update([
            'scoring_type' => 'monthly_flat',
            'comparison_type' => 'lte',
            'label' => 'IN TIME', // As requested in the image title
            'unit' => 'late count'
        ]);

        // Clear existing tiers for this role/metric
        DailyScoringTier::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();
        PeriodTarget::where('role_id', $role->id)->where('metric_id', $metric->id)->delete();

        // 2. Monthly Targets (Based on "Lower is Better")
        // Logic: value <= min_value
        // If lates <= 4 -> 15 pts
        // If lates <= 5 -> 10 pts
        // If lates <= 6 -> 7 pts
        // If lates <= 7 -> 4 pts
        $monthlyTargets = [
            ['period_type' => 'monthly', 'tier_label' => 'green', 'min_value' => 4, 'points_awarded' => 15],
            ['period_type' => 'monthly', 'tier_label' => 'yellow', 'min_value' => 5, 'points_awarded' => 10],
            ['period_type' => 'monthly', 'tier_label' => 'red', 'min_value' => 6, 'points_awarded' => 7],
            ['period_type' => 'monthly', 'tier_label' => 'grey', 'min_value' => 7, 'points_awarded' => 4],
        ];

        foreach ($monthlyTargets as $target) {
            PeriodTarget::create(array_merge($target, [
                'role_id' => $role->id,
                'metric_id' => $metric->id,
                'updated_by' => 1
            ]));
        }

        $this->command->info("Scoring tiers and targets for 'Retail Sales' - 'IN TIME' (Inverse Scoring) implemented successfully!");
    }
}
