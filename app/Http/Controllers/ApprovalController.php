<?php

namespace App\Http\Controllers;

use App\Models\Slip;
use App\Models\Attendance;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    /**
     * Show the approval queue: pending slips + attendance.
     */
    public function index()
    {
        $pendingSlips = Slip::with(['user', 'metric'])
            ->whereHas('metric', fn($q) => $q->whereNotIn('key', ['attendance', 'mixing_thinners']))
            ->where('status', 'pending')
            ->orderBy('date', 'desc')
            ->get();

        // Attach combined thinner details to NC and Enamel thinner rows for clear preview
        $mixingMetric = \App\Models\Metric::where('key', 'mixing_thinners')->first();
        if ($mixingMetric) {
            $userDateKeys = $pendingSlips->whereIn('metric.key', ['nc_thinner_mixing', 'enamel_thinner_mixing'])
                ->map(fn($s) => $s->user_id . '_' . $s->date)
                ->unique()
                ->values();

            if ($userDateKeys->isNotEmpty()) {
                $mixingSlips = Slip::where('metric_id', $mixingMetric->id)
                    ->get()
                    ->keyBy(fn($s) => $s->user_id . '_' . $s->date);

                foreach ($pendingSlips as $slip) {
                    if (in_array($slip->metric->key, ['nc_thinner_mixing', 'enamel_thinner_mixing'])) {
                        $key = $slip->user_id . '_' . $slip->date;
                        $mSlip = $mixingSlips->get($key);
                        if ($mSlip) {
                            $slip->combined_thinner_litres = (float) $mSlip->value;
                            $slip->combined_thinner_points = (float) $mSlip->daily_points_earned;
                        }
                    }
                }
            }
        }

        $pendingAttendance = Attendance::with('user')
            ->where('approval_status', 'pending')
            ->orderBy('date', 'desc')
            ->get();

        $allEmployees = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))
            ->select('id', 'name')
            ->get();

        return Inertia::render('Approvals/Index', [
            'pendingSlips'      => $pendingSlips,
            'pendingAttendance' => $pendingAttendance,
            'allEmployees'      => $allEmployees,
        ]);
    }

    /**
     * Approve a slip (locks it from user editing).
     */
    public function approveSlip(Request $request, Slip $slip)
    {
        $slip->loadMissing('metric');

        $slip->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'comment'     => $request->comment,
        ]);

        ScoringService::updateMonthScores($slip->user, $slip->date);

        // If this is an attendance slip, sync back to Attendance table
        if ($slip->metric?->key === 'attendance') {
            Attendance::where('user_id', $slip->user_id)
                ->where('date', $slip->date)
                ->update(['approval_status' => 'approved', 'approved_by' => auth()->id()]);
        }

        // Auto-sync mixing_thinners if this is NC or Enamel thinner
        if (in_array($slip->metric?->key, ['nc_thinner_mixing', 'enamel_thinner_mixing'])) {
            $this->syncMixingThinnersSlip($slip->user_id, $slip->date, auth()->id());
        }

        return back()->with('success', 'Slip approved.');
    }

    /**
     * Reject a slip (user can resubmit).
     */
    public function rejectSlip(Request $request, Slip $slip)
    {
        $slip->loadMissing('metric');

        $slip->update([
            'status'      => 'rejected',
            'approved_by' => auth()->id(),
            'comment'     => $request->comment,
        ]);

        ScoringService::updateMonthScores($slip->user, $slip->date);

        // If this is an attendance slip, sync back to Attendance table
        if ($slip->metric?->key === 'attendance') {
            Attendance::where('user_id', $slip->user_id)
                ->where('date', $slip->date)
                ->update([
                    'approval_status' => 'rejected', 
                    'approved_by' => auth()->id(),
                    'rejection_reason' => $request->comment
                ]);
        }

        // Auto-sync mixing_thinners if this is NC or Enamel thinner
        if (in_array($slip->metric?->key, ['nc_thinner_mixing', 'enamel_thinner_mixing'])) {
            $this->syncMixingThinnersSlip($slip->user_id, $slip->date, auth()->id());
        }

        return back()->with('success', 'Slip rejected.');
    }

    /**
     * Approve attendance.
     */
    public function approveAttendance(Request $request, Attendance $attendance)
    {
        $attendance->update([
            'approval_status' => 'approved',
            'approved_by'     => auth()->id(),
        ]);

        // Sync with Slip table (Attendance + Late)
        $attendanceMetric = \App\Models\Metric::where('key', 'attendance')->first();
        $lateMetric       = \App\Models\Metric::where('key', 'late')->first();
        
        Slip::where('user_id', $attendance->user_id)
            ->whereIn('metric_id', array_filter([$attendanceMetric?->id, $lateMetric?->id]))
            ->where('date', $attendance->date)
            ->update(['status' => 'approved', 'approved_by' => auth()->id()]);

        ScoringService::updateMonthScores($attendance->user, $attendance->date);

        return back()->with('success', 'Attendance approved.');
    }

    /**
     * Reject attendance.
     */
    public function rejectAttendance(Request $request, Attendance $attendance)
    {
        $attendance->update([
            'approval_status'  => 'rejected',
            'approved_by'      => auth()->id(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Sync with Slip table: Delete Attendance AND Late slips
        $attendanceMetric = \App\Models\Metric::where('key', 'attendance')->first();
        $lateMetric       = \App\Models\Metric::where('key', 'late')->first();
        
        Slip::where('user_id', $attendance->user_id)
            ->whereIn('metric_id', array_filter([$attendanceMetric?->id, $lateMetric?->id]))
            ->where('date', $attendance->date)
            ->delete();

        return back()->with('success', 'Attendance rejected.');
    }

    public function bulkApproveSlips(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        $slips = Slip::with(['metric', 'user'])->whereIn('id', $request->ids)->where('status', 'pending')->get();
        $thinnerSyncPairs = [];

        foreach ($slips as $slip) {
            $slip->update([
                'status' => 'approved',
                'approved_by' => auth()->id()
            ]);
            
            if ($slip->metric?->key === 'attendance') {
                Attendance::where('user_id', $slip->user_id)
                    ->where('date', $slip->date)
                    ->update(['approval_status' => 'approved', 'approved_by' => auth()->id()]);
            }

            if (in_array($slip->metric?->key, ['nc_thinner_mixing', 'enamel_thinner_mixing'])) {
                $dateStr = \Carbon\Carbon::parse($slip->date)->toDateString();
                $thinnerSyncPairs[$slip->user_id . '_' . $dateStr] = [
                    'user_id' => $slip->user_id,
                    'date'    => $dateStr,
                ];
            }

            ScoringService::updateMonthScores($slip->user, $slip->date);
        }

        foreach ($thinnerSyncPairs as $pair) {
            $this->syncMixingThinnersSlip($pair['user_id'], $pair['date'], auth()->id());
        }

        return back()->with('success', count($request->ids) . ' slips approved.');
    }

    public function bulkRejectSlips(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'comment' => 'required|string']);
        $slips = Slip::with(['metric', 'user'])->whereIn('id', $request->ids)->where('status', 'pending')->get();
        $thinnerSyncPairs = [];

        foreach ($slips as $slip) {
            $slip->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'comment' => $request->comment
            ]);

            if ($slip->metric?->key === 'attendance') {
                Attendance::where('user_id', $slip->user_id)
                    ->where('date', $slip->date)
                    ->update([
                        'approval_status' => 'rejected', 
                        'approved_by' => auth()->id(),
                        'rejection_reason' => $request->comment
                    ]);
            }

            if (in_array($slip->metric?->key, ['nc_thinner_mixing', 'enamel_thinner_mixing'])) {
                $dateStr = \Carbon\Carbon::parse($slip->date)->toDateString();
                $thinnerSyncPairs[$slip->user_id . '_' . $dateStr] = [
                    'user_id' => $slip->user_id,
                    'date'    => $dateStr,
                ];
            }

            ScoringService::updateMonthScores($slip->user, $slip->date);
        }

        foreach ($thinnerSyncPairs as $pair) {
            $this->syncMixingThinnersSlip($pair['user_id'], $pair['date'], auth()->id());
        }

        return back()->with('success', count($request->ids) . ' slips rejected.');
    }

    public function bulkApproveAttendance(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        
        $attendances = Attendance::whereIn('id', $request->ids)->where('approval_status', 'pending')->get();
        
        foreach ($attendances as $attendance) {
            $attendance->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id()
            ]);

            // Sync with Slip table (Attendance + Late)
            $attendanceMetric = \App\Models\Metric::where('key', 'attendance')->first();
            $lateMetric       = \App\Models\Metric::where('key', 'late')->first();
            
            Slip::where('user_id', $attendance->user_id)
                ->whereIn('metric_id', array_filter([$attendanceMetric?->id, $lateMetric?->id]))
                ->where('date', $attendance->date)
                ->update(['status' => 'approved', 'approved_by' => auth()->id()]);

            ScoringService::updateMonthScores($attendance->user, $attendance->date);
        }

        return back()->with('success', count($request->ids) . ' attendance records approved.');
    }

    public function bulkRejectAttendance(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        
        $attendances = Attendance::whereIn('id', $request->ids)->where('approval_status', 'pending')->get();
        
        foreach ($attendances as $attendance) {
            $attendance->update([
                'approval_status'  => 'rejected',
                'approved_by'      => auth()->id(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Sync with Slip table: Delete Attendance AND Late slips
            $attendanceMetric = \App\Models\Metric::where('key', 'attendance')->first();
            $lateMetric       = \App\Models\Metric::where('key', 'late')->first();
            
            Slip::where('user_id', $attendance->user_id)
                ->whereIn('metric_id', array_filter([$attendanceMetric?->id, $lateMetric?->id]))
                ->where('date', $attendance->date)
                ->delete();
        }

        return back()->with('success', count($request->ids) . ' attendance records rejected.');
    }

    /**
     * Automatically aggregate and synchronize 'mixing_thinners' slip based on
     * the constituent 'nc_thinner_mixing' and 'enamel_thinner_mixing' slips.
     */
    private function syncMixingThinnersSlip($userId, $date, $adminId)
    {
        $dateStr = \Carbon\Carbon::parse($date)->toDateString();

        $ncMetric     = \App\Models\Metric::where('key', 'nc_thinner_mixing')->first();
        $enamelMetric = \App\Models\Metric::where('key', 'enamel_thinner_mixing')->first();
        $mixingMetric = \App\Models\Metric::where('key', 'mixing_thinners')->first();

        if (!$mixingMetric) {
            return;
        }

        $thinnerSlips = Slip::where('user_id', $userId)
            ->whereIn('metric_id', array_filter([$ncMetric?->id, $enamelMetric?->id]))
            ->where('date', $dateStr)
            ->get();

        if ($thinnerSlips->isEmpty()) {
            return;
        }

        $user = \App\Models\User::find($userId);
        $userRole = $user?->roles()->first();

        // 1. If any submitted thinner slip is still pending, mixing_thinners remains pending
        $hasPending = $thinnerSlips->contains('status', 'pending');
        if ($hasPending) {
            $totalLitres = (float) $thinnerSlips->sum('value');
            $points = 0;
            $mixingTiers = \App\Models\DailyScoringTier::where('metric_id', $mixingMetric->id)
                ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                ->orderBy('min_value', 'desc')
                ->get();
            if ($mixingTiers->isEmpty()) {
                $mixingTiers = \App\Models\DailyScoringTier::where('metric_id', $mixingMetric->id)
                    ->orderBy('min_value', 'desc')
                    ->get();
            }
            foreach ($mixingTiers as $t) {
                if ($totalLitres >= (float)$t->min_value) {
                    $points = (float)$t->daily_points;
                    break;
                }
            }

            Slip::updateOrCreate(
                ['user_id' => $userId, 'metric_id' => $mixingMetric->id, 'date' => $dateStr],
                [
                    'value'               => $totalLitres,
                    'daily_points_earned' => $points,
                    'status'              => 'pending',
                    'approved_by'         => null,
                ]
            );
            return;
        }

        // 2. All thinner slips have been reviewed (approved or rejected)
        $approvedSlips = $thinnerSlips->where('status', 'approved');
        $approvedSum   = (float) $approvedSlips->sum('value');

        if ($approvedSum > 0) {
            $points = 0;
            $mixingTiers = \App\Models\DailyScoringTier::where('metric_id', $mixingMetric->id)
                ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                ->orderBy('min_value', 'desc')
                ->get();
            if ($mixingTiers->isEmpty()) {
                $mixingTiers = \App\Models\DailyScoringTier::where('metric_id', $mixingMetric->id)
                    ->orderBy('min_value', 'desc')
                    ->get();
            }
            foreach ($mixingTiers as $t) {
                if ($approvedSum >= (float)$t->min_value) {
                    $points = (float)$t->daily_points;
                    break;
                }
            }

            Slip::updateOrCreate(
                ['user_id' => $userId, 'metric_id' => $mixingMetric->id, 'date' => $dateStr],
                [
                    'value'               => $approvedSum,
                    'daily_points_earned' => $points,
                    'status'              => 'approved',
                    'approved_by'         => $adminId,
                ]
            );
        } else {
            // All slips were rejected
            Slip::updateOrCreate(
                ['user_id' => $userId, 'metric_id' => $mixingMetric->id, 'date' => $dateStr],
                [
                    'value'               => 0,
                    'daily_points_earned' => 0,
                    'status'              => 'rejected',
                    'approved_by'         => $adminId,
                ]
            );
        }

        if ($user) {
            ScoringService::updateMonthScores($user, $dateStr);
        }
    }
}
