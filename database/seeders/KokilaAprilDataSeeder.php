<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\Attendance;
use App\Models\PerformanceScore;
use App\Models\DailyScoringTier;
use App\Services\ScoringService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KokilaAprilDataSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('name', 'like', '%Kokila%')->first();
        if (!$user) {
            $this->command->error('User Kokila not found!');
            return;
        }

        $roleId = $user->roles()->first()?->id;
        $month = '2026-04';
        $startDate = Carbon::parse($month . '-01');
        $endDate = Carbon::parse($month . '-30');

        $this->command->info("Cleaning existing data for {$user->name} in {$month}...");
        
        Slip::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->delete();
            
        Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->delete();
            
        PerformanceScore::where('user_id', $user->id)
            ->whereBetween('period_start', [$startDate->toDateString(), $endDate->toDateString()])
            ->delete();

        $metrics = Metric::whereIn('key', [
            'sales', 'collection', 'colour_matching', 'customer_handling', 
            'attendance', 'late', 'panel_collection', 'old_stock_enamel', 
            'old_stock_pu', 'stock_checking'
        ])->get()->keyBy('key');

        $this->command->info("Seeding realistic data for April 2026...");

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Skip Sundays
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            // Attendance Pattern
            $status = 'present';
            $checkIn = '09:00';
            
            // 2 Leaves: April 10, April 23
            if ($date->day === 10 || $date->day === 23) {
                $status = 'absent';
            } 
            // 1 Half Day: April 17
            elseif ($date->day === 17) {
                $status = 'half_day';
                $checkIn = '10:30';
            }
            // 5 Lates: April 3, 8, 14, 21, 28
            elseif (in_array($date->day, [3, 8, 14, 21, 28])) {
                $status = 'late';
                $checkIn = '09:25';
            }

            // 1. Create Attendance Record
            if ($status !== 'absent') {
                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                    'check_in_time' => $checkIn,
                    'status' => $status,
                    'approval_status' => 'approved',
                    'latitude' => 19.0760,
                    'longitude' => 72.8777,
                    'is_within_geofence' => true,
                    'distance_from_center' => rand(10, 50),
                ]);
            }

            // 2. Seed Slips for all metrics
            $dailySales = 0;
            foreach ($metrics as $key => $metric) {
                $value = 0;
                $approved = true;

                switch ($key) {
                    case 'attendance':
                        if ($status === 'absent') $value = 0;
                        elseif ($status === 'half_day') $value = 0.5;
                        else $value = 1.0;
                        break;

                    case 'late':
                        if ($status === 'late') $value = 1;
                        else continue 2; // Only create late slip if actually late
                        break;

                    case 'sales':
                        if ($status === 'absent') break;
                        $dailySales = ($date->dayOfWeek === Carbon::SATURDAY) ? rand(8000, 12000) : rand(12000, 22000);
                        $value = $dailySales;
                        break;

                    case 'collection':
                        if ($status === 'absent' || $dailySales <= 0) break;
                        // Collection is a percentage of sales. Generate 60%-120% collection.
                        $collectionPercent = rand(60, 120);
                        $value = round($dailySales * $collectionPercent / 100);
                        break;

                    case 'colour_matching':
                        if ($status === 'absent') break;
                        $value = rand(25, 50) / 10; // 2.5 to 5.0
                        break;

                    case 'customer_handling':
                        if ($status === 'absent') break;
                        $value = rand(7, 12);
                        break;

                    case 'panel_collection':
                        if ($status === 'absent') break;
                        if (rand(1, 3) === 1) $value = rand(500, 2500); // Reported every 3rd day
                        break;

                    case 'old_stock_enamel':
                        if ($status === 'absent') break;
                        $value = rand(8, 18) / 10; // 0.8 to 1.8
                        break;

                    case 'old_stock_pu':
                        if ($status === 'absent') break;
                        if (rand(1, 3) === 1) $value = rand(200, 1500);
                        break;

                    case 'stock_checking':
                        if ($status === 'absent') break;
                        if (in_array($date->day, [5, 11, 18, 25, 29])) $value = 1;
                        break;
                }

                if ($value > 0 || $key === 'attendance' || ($key === 'late' && $status === 'late')) {
                    // Calculate comparison value for percentage metrics
                    $comparisonValue = $value;
                    if ($metric->value_type === 'percentage' && $metric->reference_metric_id) {
                        if ($key === 'collection' && $dailySales > 0) {
                            $comparisonValue = ($value / $dailySales) * 100;
                        } else {
                            $comparisonValue = 0;
                        }
                    }

                    // Calculate Daily Points
                    $dailyPoints = 0;
                    $tier = DailyScoringTier::where('metric_id', $metric->id)
                        ->where('role_id', $roleId)
                        ->where('min_value', '<=', $comparisonValue)
                        ->orderBy('min_value', 'desc')
                        ->first();
                    
                    if ($tier) {
                        $dailyPoints = $tier->daily_points;
                    }

                    Slip::create([
                        'user_id' => $user->id,
                        'metric_id' => $metric->id,
                        'date' => $date->toDateString(),
                        'value' => $value,
                        'daily_points_earned' => $dailyPoints,
                        'status' => 'approved',
                        'approved_by' => 1,
                        'comment' => 'Realistic auto-generated seed data',
                    ]);
                }
            }
        }

        $this->command->info("Recalculating monthly scores...");
        ScoringService::updateMonthScores($user, $month);

        $this->command->info("Successfully seeded data for Kokila.");
    }
}
