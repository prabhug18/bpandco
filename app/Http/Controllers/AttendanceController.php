<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Slip;
use App\Models\Metric;
use Illuminate\Http\Request;
use App\Models\AppSetting;
use App\Services\ScoringService;
use Inertia\Inertia;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $records = Attendance::where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return Inertia::render('Attendance/Index', [
            'records'   => $records,
            'today'     => Carbon::today()->toDateString(),
            'yesterday' => Carbon::yesterday()->toDateString(),
            'rules'     => [
                'work_start' => AppSetting::get('attendance_work_start', '09:00'),
                'grace_end'  => AppSetting::get('attendance_grace_end', '09:15'),
                'half_day'   => AppSetting::get('attendance_half_day', '10:00'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        // ... validation and guards ...
        $request->validate([
            'date'          => 'required|date',
            'check_in_time' => 'required',
            'image'         => 'required|image|max:5120',
        ]);

        $user = auth()->user();
        $date = Carbon::parse($request->date);
        $now  = Carbon::now();

        if ($date->isYesterday() && $now->hour >= 12) {
            return back()->withErrors(['date' => 'Deadline passed. Yesterday\'s attendance must be submitted before 12:00 PM today.']);
        }

        $existing = Attendance::where('user_id', $user->id)->where('date', $date->toDateString())->first();
        
        // If it's not today/yesterday, we ONLY allow it if it's a resubmission of a rejected record
        if (!$date->isToday() && !$date->isYesterday()) {
            if (!$existing || $existing->approval_status !== 'rejected') {
                return back()->withErrors(['date' => 'Only today\'s or yesterday\'s date is allowed for new submissions.']);
            }
        }

        $isHoliday = Holiday::where('is_active', true)
            ->where('date', $date->toDateString())
            ->where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->exists();

        if ($isHoliday && (!$existing || $existing->approval_status !== 'rejected')) {
            return back()->withErrors(['date' => 'This date is a holiday. Attendance not required.']);
        }

        if ($existing && $existing->approval_status === 'approved') {
            return back()->withErrors(['date' => 'Attendance already approved. Cannot change.']);
        }

        // Determine attendance status from DB settings
        // Standardize time format (handle H:i:s or H:i)
        $formattedTime = Carbon::parse($request->check_in_time)->format('H:i');
        $checkIn = Carbon::createFromFormat('H:i', $formattedTime);
        $workStartTime = AppSetting::get('attendance_work_start', '09:00');
        $graceEndTime  = AppSetting::get('attendance_grace_end', '09:15');
        $halfDayTime   = AppSetting::get('attendance_half_day', '10:00');

        $workStart = Carbon::createFromFormat('H:i', $workStartTime);
        $graceEnd  = Carbon::createFromFormat('H:i', $graceEndTime);
        $halfDayThreshold = Carbon::createFromFormat('H:i', $halfDayTime);

        $status = 'present';
        if ($checkIn->gt($halfDayThreshold)) {
            $status = 'half_day';
        } elseif ($checkIn->gt($graceEnd)) {
            $status = 'late';
        }

        $imagePath = $request->file('image')->store('attendance', 'public');

        Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            [
                'check_in_time'   => $formattedTime,
                'image_path'      => $imagePath,
                'status'          => $status,
                'approval_status' => 'pending',
            ]
        );

        // Dynamic 'Attendance' Slip Logic:
        $attendanceMetric = Metric::where('key', 'attendance')->first();
        if ($attendanceMetric) {
            $val = match($status) {
                'half_day' => 0.5,
                default    => 1.0,
            };

            // Calculate daily points based on tiers
            $tiers = \App\Models\DailyScoringTier::where('metric_id', $attendanceMetric->id)
                ->orderBy('min_value', 'desc')
                ->get();

            $dailyPoints = 0;
            foreach ($tiers as $tier) {
                if ($val >= $tier->min_value) {
                    $dailyPoints = $tier->daily_points;
                    break;
                }
            }

            Slip::updateOrCreate(
                ['user_id' => $user->id, 'metric_id' => $attendanceMetric->id, 'date' => $date->toDateString()],
                ['value' => $val, 'daily_points_earned' => $dailyPoints, 'status' => 'pending']
            );
        }

        // Dynamic 'Late' Slip Logic:
        // Find the 'Late' metric (id: 7)
        $lateMetric = Metric::where('key', 'late')->first();
        
        if ($lateMetric) {
            if ($status === 'late') {
                // Auto-create an APPROVED slip for the 'Late' metric (consistent with previous logic)
                Slip::updateOrCreate(
                    ['user_id' => $user->id, 'metric_id' => $lateMetric->id, 'date' => $date->toDateString()],
                    ['value' => 1, 'status' => 'approved', 'comment' => 'Automated late entry from Attendance']
                );
            } else {
                // If they re-mark and are NOT late (or are half-day), remove any existing late slip for this day
                Slip::where('user_id', $user->id)->where('metric_id', $lateMetric->id)->where('date', $date->toDateString())->delete();
            }
            
            ScoringService::updateMonthScores($user, $date->toDateString());
        }

        return back()->with('success', 'Attendance submitted. Pending approval.');
    }

    public function create() {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
