<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use Illuminate\Support\Facades\DB;

class AttendanceScoringSeeder extends Seeder
{
    public function run()
    {
        // 1. Update the Attendance Metric
        $attendance = Metric::where('key', 'attendance')->first();
        if (!$attendance) {
            $attendance = Metric::create([
                'key' => 'attendance',
                'label' => 'Attendance',
                'unit' => 'days',
                'is_active' => true,
                'scoring_type' => '10_20_30_days'
            ]);
        } else {
            $attendance->update([
                'unit' => 'days',
                'scoring_type' => '10_20_30_days'
            ]);
        }

        // 2. Clear existing tiers/targets for attendance to avoid duplicates
        DailyScoringTier::where('metric_id', $attendance->id)->delete();
        PeriodTarget::where('metric_id', $attendance->id)->delete();

        // 3. Set up Daily Scoring Tiers
        // (P-0.67) (A-0.00)
        DailyScoringTier::create([
            'metric_id' => $attendance->id,
            'min_value' => 1.0,
            'daily_points' => 0.67,
            'updated_by' => 1
        ]);
        
        DailyScoringTier::create([
            'metric_id' => $attendance->id,
            'min_value' => 0.5,
            'daily_points' => 0.33, // Half Day
            'updated_by' => 1
        ]);

        // 4. Set up Period Targets (Cumulative)
        
        // --- 10 Days Target ---
        // (8-20P)(7-15P)(6-10P)(5-5P)
        $targets10 = [
            ['tier' => 'green',  'min' => 8, 'pts' => 20],
            ['tier' => 'yellow', 'min' => 7, 'pts' => 15],
            ['tier' => 'red',    'min' => 6, 'pts' => 10],
            ['tier' => 'grey',   'min' => 5, 'pts' => 5],
        ];
        foreach ($targets10 as $t) {
            PeriodTarget::create([
                'metric_id' => $attendance->id,
                'period_type' => '10_days',
                'tier_label' => $t['tier'],
                'min_value' => $t['min'],
                'points_awarded' => $t['pts'],
                'updated_by' => 1
            ]);
        }

        // --- 20 Days Target ---
        // (16P-20P)(15-15P)(14-10P)(13-5P)
        $targets20 = [
            ['tier' => 'green',  'min' => 16, 'pts' => 20],
            ['tier' => 'yellow', 'min' => 15, 'pts' => 15],
            ['tier' => 'red',    'min' => 14, 'pts' => 10],
            ['tier' => 'grey',   'min' => 13, 'pts' => 5],
        ];
        foreach ($targets20 as $t) {
            PeriodTarget::create([
                'metric_id' => $attendance->id,
                'period_type' => '20_days',
                'tier_label' => $t['tier'],
                'min_value' => $t['min'],
                'points_awarded' => $t['pts'],
                'updated_by' => 1
            ]);
        }

        // --- 30 Days Target ---
        // (25P-20P)(24P-15P)(23P-10P)(22P-5P)
        $targets30 = [
            ['tier' => 'green',  'min' => 25, 'pts' => 20],
            ['tier' => 'yellow', 'min' => 24, 'pts' => 15],
            ['tier' => 'red',    'min' => 23, 'pts' => 10],
            ['tier' => 'grey',   'min' => 22, 'pts' => 5],
        ];
        foreach ($targets30 as $t) {
            PeriodTarget::create([
                'metric_id' => $attendance->id,
                'period_type' => '30_days',
                'tier_label' => $t['tier'],
                'min_value' => $t['min'],
                'points_awarded' => $t['pts'],
                'updated_by' => 1
            ]);
        }
    }
}
