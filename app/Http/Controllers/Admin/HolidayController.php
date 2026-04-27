<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index()
    {
        $holidays = Holiday::with('user')->orderBy('date', 'desc')->get();
        $users = User::all(['id', 'name']); // For dropdown when assigning specific leaves

        return Inertia::render('Admin/Holidays/Index', [
            'holidays' => $holidays,
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:255',
            'type' => 'required|in:global_holiday,sick_leave,weekly_off',
            'user_id' => 'required_if:type,sick_leave,weekly_off|nullable|exists:users,id',
            'is_active' => 'boolean'
        ]);

        $holiday = Holiday::create([
            'date' => $request->date,
            'reason' => $request->reason,
            'type' => $request->type,
            'user_id' => $request->user_id,
            'is_active' => $request->is_active ?? true
        ]);

        $this->syncHolidayAttendance($holiday);

        return back()->with('success', 'Holiday/Leave configured successfully.');
    }

    private function syncHolidayAttendance(Holiday $holiday)
    {
        $attendanceMetric = \App\Models\Metric::where('key', 'attendance')->first();
        if (!$attendanceMetric) return;

        $attendancePoints = \App\Models\DailyScoringTier::where('metric_id', $attendanceMetric->id)
            ->where('min_value', 1.0)
            ->value('daily_points') ?? 0.67;

        if ($holiday->user_id) {
            $users = \App\Models\User::where('id', $holiday->user_id)->get();
        } else {
            // Only credit staff, not admins
            $users = \App\Models\User::whereDoesntHave('roles', fn($q) => $q->where('name', 'admin'))->get();
        }

        foreach ($users as $user) {
            \App\Models\Slip::updateOrCreate(
                ['user_id' => $user->id, 'metric_id' => $attendanceMetric->id, 'date' => $holiday->date],
                [
                    'value' => 1.0, 
                    'daily_points_earned' => $attendancePoints, 
                    'status' => 'approved', 
                    'comment' => 'Holiday Credit: ' . $holiday->reason
                ]
            );
        }
    }

    public function destroy(Holiday $holiday)
    {
        // Remove associated holiday attendance slips
        $attendanceMetric = \App\Models\Metric::where('key', 'attendance')->first();
        if ($attendanceMetric) {
            \App\Models\Slip::where('metric_id', $attendanceMetric->id)
                ->where('date', $holiday->date)
                ->where('comment', 'LIKE', 'Holiday Credit%')
                ->delete();
        }

        $holiday->delete();
        return back()->with('success', 'Holiday/Leave removed.');
    }
}
