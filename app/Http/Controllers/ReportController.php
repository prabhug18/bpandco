<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\Attendance;
use App\Models\PerformanceScore;
use App\Models\SummaryReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Individual Employee Excel-style Report
     */
    public function individual(Request $request)
    {
        $userId = $request->user_id;
        $user   = $userId ? User::find($userId) : null;
        
        // Only auto-load for non-admins if no user_id provided
        if (!$userId && !auth()->user()->hasAnyPermission(['approve slips', 'configure metrics'])) {
            $user = auth()->user();
        }

        $allEmployees = [];
        if (auth()->user()->hasAnyPermission(['approve slips', 'configure metrics'])) {
            $allEmployees = User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))
                ->orderBy('name')
                ->get();
        }

        $period = $request->period ?? 'this_month';
        $dateFrom = $request->date_from ?? $request->from; 
        $dateTo   = $request->date_to   ?? $request->to;

        if (!$dateFrom || !$dateTo) {
            $monthStr = $request->month ? Carbon::parse($request->month)->format('Y-m') : Carbon::today()->format('Y-m');
            [$dateFrom, $dateTo] = $this->resolvePeriod($period, $monthStr);
        }
        
        $carbonFrom = Carbon::parse($dateFrom);
        $monthStr   = $carbonFrom->format('Y-m');
        $daysInMonth = $carbonFrom->daysInMonth;

        $metrics = [];
        $slips = [];
        $attendance = [];
        $scores = [];

        if ($user) {
            $user->load('roles');
            $userRoleIds = $user->roles->pluck('id');
            // Get all metrics for this user's role, eagerly loading scoped tiers
            $metrics = Metric::with([
                'periodTargets' => fn($q) => $q->whereIn('role_id', $userRoleIds),
                'dailyScoringTiers' => fn($q) => $q->whereIn('role_id', $userRoleIds),
            ])->whereHas('roles', fn($q) => $q->whereIn('roles.id', $userRoleIds))->get();

            // Get all approved slips for this period
            $slips = Slip::where('user_id', $user->id)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->where('status', 'approved')
                ->get()
                ->groupBy('date');

            // Get attendance for the period
            $attendance = Attendance::where('user_id', $user->id)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->get()
                ->keyBy('date');

            // Get performance score aggregates
            $scores = PerformanceScore::where('user_id', $user->id)
                ->whereBetween('period_start', [$dateFrom, $dateTo])
                ->get();
        }

        return Inertia::render('Reports/Individual', [
            'employee'     => $user,
            'allEmployees' => $allEmployees,
            'metrics'      => $metrics,
            'slips'        => (object)$slips,
            'attendance'   => (object)$attendance,
            'scores'       => $scores,
            'month'        => $monthStr,
            'days'         => $daysInMonth,
            'period'       => $period,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'metric_id'    => $request->metric_id,
        ]);
    }

    private function resolvePeriod(string $period, string $monthStr): array
    {
        $month = Carbon::parse($monthStr);
        $today = Carbon::today();

        return match($period) {
            '10_days'     => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->startOfMonth()->addDays(9)->toDateString()],
            '20_days'     => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->startOfMonth()->addDays(19)->toDateString()],
            '30_days'     => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->startOfMonth()->addDays(29)->toDateString()],
            '3_months'    => [$today->copy()->subMonths(3)->toDateString(), $today->toDateString()],
            '6_months'    => [$today->copy()->subMonths(6)->toDateString(), $today->toDateString()],
            '1_year'      => [$today->copy()->subYear()->toDateString(), $today->toDateString()],
            default       => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()], // this_month
        };
    }

    public function team(Request $request)
    {
        $month  = $request->month ? Carbon::parse($request->month) : Carbon::today();
        $period = $request->period ?? '30_days'; // default: 30-day consolidated
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd   = $month->copy()->endOfMonth();

        // Pass all active metrics so Vue can generate dynamic columns
        $metrics = Metric::all();

        $users = User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))->get();

        // Get performance score aggregates for the selected period type
        $scores = PerformanceScore::where('period_type', $period)
            ->whereBetween('period_start', [$monthStart, $monthEnd])
            ->get();

        return Inertia::render('Reports/Team', [
            'employees' => $users,
            'metrics'   => $metrics,
            'scores'    => $scores,
            'month'     => $month->format('Y-m'),
            'period'    => $period,
        ]);
    }
}
