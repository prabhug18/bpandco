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
            ->whereHas('metric', fn($q) => $q->where('key', '!=', 'attendance'))
            ->where('status', 'pending')
            ->orderBy('date', 'desc')
            ->get();

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
        $slip->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'comment'     => $request->comment,
        ]);

        ScoringService::updateMonthScores($slip->user, $slip->date);

        // If this is an attendance slip, sync back to Attendance table
        if ($slip->metric->key === 'attendance') {
            Attendance::where('user_id', $slip->user_id)
                ->where('date', $slip->date)
                ->update(['approval_status' => 'approved', 'approved_by' => auth()->id()]);
        }

        return back()->with('success', 'Slip approved.');
    }

    /**
     * Reject a slip (user can resubmit).
     */
    public function rejectSlip(Request $request, Slip $slip)
    {
        $slip->update([
            'status'      => 'rejected',
            'approved_by' => auth()->id(),
            'comment'     => $request->comment,
        ]);

        ScoringService::updateMonthScores($slip->user, $slip->date);

        // If this is an attendance slip, sync back to Attendance table
        if ($slip->metric->key === 'attendance') {
            Attendance::where('user_id', $slip->user_id)
                ->where('date', $slip->date)
                ->update([
                    'approval_status' => 'rejected', 
                    'approved_by' => auth()->id(),
                    'rejection_reason' => $request->comment
                ]);
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
        $slips = Slip::with('metric')->whereIn('id', $request->ids)->where('status', 'pending')->get();
        foreach ($slips as $slip) {
            $slip->update([
                'status' => 'approved',
                'approved_by' => auth()->id()
            ]);
            
            if ($slip->metric->key === 'attendance') {
                Attendance::where('user_id', $slip->user_id)
                    ->where('date', $slip->date)
                    ->update(['approval_status' => 'approved', 'approved_by' => auth()->id()]);
            }

            ScoringService::updateMonthScores($slip->user, $slip->date);
        }
        return back()->with('success', count($request->ids) . ' slips approved.');
    }

    public function bulkRejectSlips(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'comment' => 'required|string']);
        $slips = Slip::with('metric')->whereIn('id', $request->ids)->where('status', 'pending')->get();
        foreach ($slips as $slip) {
            $slip->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'comment' => $request->comment
            ]);

            if ($slip->metric->key === 'attendance') {
                Attendance::where('user_id', $slip->user_id)
                    ->where('date', $slip->date)
                    ->update([
                        'approval_status' => 'rejected', 
                        'approved_by' => auth()->id(),
                        'rejection_reason' => $request->comment
                    ]);
            }

            ScoringService::updateMonthScores($slip->user, $slip->date);
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
}
