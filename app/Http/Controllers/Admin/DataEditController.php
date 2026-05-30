<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Metric;
use App\Models\PerformanceScore;
use App\Models\Slip;
use App\Models\Attendance;
use App\Models\DailyScoringTier;
use Inertia\Inertia;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DataEditController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : $today->copy()->startOfMonth();
        $dateTo   = $request->date_to ? Carbon::parse($request->date_to) : $today->copy()->endOfMonth();
        $userId   = $request->user_id;

        $users = User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))->get();
        $metrics = Metric::all();

        $data = [];

        if (!$userId) {
            // View A: All Users (Summary)
            // Get performance score aggregates for the selected period
            // Since period is custom, we sum the daily points
            $slips = Slip::whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->where('status', 'approved')
                ->get()
                ->groupBy('user_id');

            foreach ($users as $user) {
                $userSlips = $slips->get($user->id, collect());
                $totalPoints = $userSlips->sum('daily_points_earned');
                
                // Determine color based on simple threshold logic
                $color = 'grey';
                if ($totalPoints >= 70) $color = 'green';
                elseif ($totalPoints >= 50) $color = 'yellow';
                elseif ($totalPoints >= 30) $color = 'red';

                $data[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'total_points' => $totalPoints,
                    'color' => $color,
                ];
            }
        } else {
            // View B: Specific User (Date-wise)
            $user = User::find($userId);
            $roleId = $user->roles()->first()?->id;

            // Load all slips and attendance for this user in the date range
            $userSlips = Slip::where('user_id', $userId)
                ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->get()
                ->groupBy('date');

            $userAttendance = Attendance::where('user_id', $userId)
                ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->get()
                ->keyBy('date');

            // Get active metrics for this user's role
            $userMetrics = Metric::whereHas('roles', fn($q) => $q->where('roles.id', $roleId))->get();

            $period = CarbonPeriod::create($dateFrom, $dateTo);
            
            // Loop through each date
            foreach ($period as $date) {
                $dateStr = $date->toDateString();
                $daySlips = $userSlips->get($dateStr, collect());
                $att = $userAttendance->get($dateStr);

                $row = [
                    'date' => $dateStr,
                    'formatted_date' => $date->format('M d, Y'),
                    'attendance' => $att ? $att->status : 'absent',
                    'metrics' => [],
                ];

                foreach ($userMetrics as $metric) {
                    $slip = $daySlips->firstWhere('metric_id', $metric->id);
                    $row['metrics'][$metric->id] = $slip ? $slip->value : null;
                }

                $data[] = $row;
            }
            
            // Send userMetrics instead of all metrics to UI
            $metrics = $userMetrics;
        }

        return Inertia::render('Admin/DataEdit/Index', [
            'users' => $users,
            'metrics' => $metrics,
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'user_id' => $userId,
            ]
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'attendance' => 'required|string',
            'metrics' => 'array',
        ]);

        $userId = $request->user_id;
        $dateStr = $request->date;
        $user = User::find($userId);
        $roleId = $user->roles()->first()?->id;

        // 1. Update Attendance
        Attendance::updateOrCreate(
            ['user_id' => $userId, 'date' => $dateStr],
            [
                'status' => $request->attendance,
                'approval_status' => 'approved',
                // other fields remain null if creating via admin
            ]
        );

        // Process Attendance Slip (if metric exists)
        $attMetric = Metric::where('key', 'attendance')->first();
        if ($attMetric) {
            $val = match($request->attendance) {
                'half_day' => 0.5,
                'absent' => 0,
                default => 1.0,
            };

            $dailyPoints = 0;
            if ($val > 0) {
                $tiers = DailyScoringTier::where('metric_id', $attMetric->id)
                    ->when($roleId, fn($q) => $q->where('role_id', $roleId))
                    ->orderBy('min_value', 'desc')
                    ->get();

                foreach ($tiers as $tier) {
                    if ($val >= $tier->min_value) {
                        $dailyPoints = $tier->daily_points;
                        break;
                    }
                }
            }

            Slip::updateOrCreate(
                ['user_id' => $userId, 'metric_id' => $attMetric->id, 'date' => $dateStr],
                ['value' => $val, 'daily_points_earned' => $dailyPoints, 'status' => 'approved']
            );
        }

        // Process Late Slip
        $lateMetric = Metric::where('key', 'late')->first();
        if ($lateMetric) {
            if ($request->attendance === 'late') {
                Slip::updateOrCreate(
                    ['user_id' => $userId, 'metric_id' => $lateMetric->id, 'date' => $dateStr],
                    ['value' => 1, 'daily_points_earned' => 0, 'status' => 'approved']
                );
            } else {
                Slip::where('user_id', $userId)->where('metric_id', $lateMetric->id)->where('date', $dateStr)->delete();
            }
        }

        // 2. Update Other Metrics
        foreach ($request->metrics as $metricId => $value) {
            $metric = Metric::find($metricId);
            if (!$metric) continue;

            if ($value === null || $value === '') {
                // Remove slip if cleared
                Slip::where('user_id', $userId)->where('metric_id', $metricId)->where('date', $dateStr)->delete();
                continue;
            }

            $numericValue = floatval($value);
            $dailyPoints = $this->calculateDailyPoints($metric, $numericValue, $roleId, $userId, $dateStr);

            Slip::updateOrCreate(
                ['user_id' => $userId, 'metric_id' => $metricId, 'date' => $dateStr],
                ['value' => $numericValue, 'daily_points_earned' => $dailyPoints, 'status' => 'approved']
            );
        }

        // 3. Recalculate Monthly Score
        \App\Services\ScoringService::updateMonthScores($user, $dateStr);

        return back()->with('success', 'Data updated successfully.');
    }

    private function calculateDailyPoints($metric, $value, $roleId, $userId, $dateStr)
    {
        $comparisonValue = $value;
        
        if ($metric->value_type === 'percentage' && $metric->reference_metric_id) {
            $referenceSlip = Slip::where('user_id', $userId)
                ->where('metric_id', $metric->reference_metric_id)
                ->where('date', $dateStr)
                ->first();
            
            if ($referenceSlip && $referenceSlip->value > 0) {
                $comparisonValue = ($value / $referenceSlip->value) * 100;
            } else {
                $comparisonValue = 0;
            }
        }

        $sortOrder = ($metric->comparison_type === 'lte') ? 'asc' : 'desc';

        $tiers = DailyScoringTier::where('metric_id', $metric->id)
            ->when($roleId, function($q) use ($roleId) {
                return $q->where('role_id', $roleId);
            })
            ->orderBy('min_value', $sortOrder)
            ->get();

        $points = 0;
        foreach ($tiers as $tier) {
            if ($metric->comparison_type === 'lte') {
                if ($comparisonValue <= $tier->min_value) {
                    $points = $tier->daily_points;
                    break;
                }
            } else {
                if ($comparisonValue >= $tier->min_value) {
                    $points = $tier->daily_points;
                    break;
                }
            }
        }

        return $points;
    }
}
