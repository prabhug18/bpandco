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

        $roleId = $user->roles()->first()?->id;

        return Inertia::render('Attendance/Index', [
            'records'   => $records,
            'today'     => Carbon::today()->toDateString(),
            'yesterday' => Carbon::yesterday()->toDateString(),
            'rules'     => [
                'work_start' => AppSetting::get('attendance_work_start', '09:00', $roleId),
                'grace_end'  => AppSetting::get('attendance_grace_end', '09:15', $roleId),
                'half_day'   => AppSetting::get('attendance_half_day', '10:00', $roleId),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image_blob' => 'required', // Base64 string from camera
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
        ]);

        $user = auth()->user();
        $roleId = $user->roles()->first()?->id;
        $now = Carbon::now();
        $date = Carbon::today();

        // 1. Geofence Validation
        $isWithinGeofence = false;
        $distanceFromCenter = null;

        $centerLat = AppSetting::get('geofence_latitude', null, $roleId);
        $centerLng = AppSetting::get('geofence_longitude', null, $roleId);
        $radius    = AppSetting::get('geofence_radius', 200, $roleId);

        if ($request->latitude && $request->longitude && $centerLat && $centerLng) {
            $distanceFromCenter = $this->calculateDistance(
                $request->latitude, 
                $request->longitude, 
                $centerLat, 
                $centerLng
            );
            $isWithinGeofence = ($distanceFromCenter <= $radius);
        }

        // 2. Attendance Status Logic
        $formattedTime = $now->format('H:i');
        $checkIn = Carbon::createFromFormat('H:i', $formattedTime);
        
        $graceEndTime  = AppSetting::get('attendance_grace_end', '09:15', $roleId);
        $halfDayTime   = AppSetting::get('attendance_half_day', '10:00', $roleId);

        $graceEnd  = Carbon::createFromFormat('H:i', $graceEndTime);
        $halfDayThreshold = Carbon::createFromFormat('H:i', $halfDayTime);

        $status = 'present';
        if ($checkIn->gt($halfDayThreshold)) {
            $status = 'half_day';
        } elseif ($checkIn->gt($graceEnd)) {
            $status = 'late';
        }

        // 3. Handle Base64 Image
        $img = $request->image_blob;
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $fileName = 'attendance/' . $user->id . '_' . time() . '.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $data);

        // 4. Save Attendance Record
        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            [
                'check_in_time'        => $formattedTime,
                'image_path'           => $fileName,
                'status'               => $status,
                'approval_status'      => 'pending',
                'latitude'             => $request->latitude,
                'longitude'            => $request->longitude,
                'is_within_geofence'   => $isWithinGeofence,
                'distance_from_center' => $distanceFromCenter,
            ]
        );

        // 5. Dynamic 'Attendance' Slip Logic
        $attendanceMetric = Metric::where('key', 'attendance')->first();
        if ($attendanceMetric) {
            $val = match($status) {
                'half_day' => 0.5,
                default    => 1.0,
            };

            $tiers = \App\Models\DailyScoringTier::where('metric_id', $attendanceMetric->id)
                ->when($roleId, fn($q) => $q->where('role_id', $roleId))
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

        // 6. Dynamic 'Late' Slip Logic
        $lateMetric = Metric::where('key', 'late')->first();
        if ($lateMetric) {
            if ($status === 'late') {
                Slip::updateOrCreate(
                    ['user_id' => $user->id, 'metric_id' => $lateMetric->id, 'date' => $date->toDateString()],
                    ['value' => 1, 'status' => 'approved', 'comment' => 'Automated late entry from Attendance']
                );
            } else {
                Slip::where('user_id', $user->id)->where('metric_id', $lateMetric->id)->where('date', $date->toDateString())->delete();
            }
            
            ScoringService::updateMonthScores($user, $date->toDateString());
        }

        $msg = 'Attendance marked successfully.';
        if (!$isWithinGeofence) {
            $msg .= ' WARNING: Captured outside office geofence.';
        }

        return back()->with('success', $msg);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    public function create() {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
