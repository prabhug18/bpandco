<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SummaryReport;
use App\Models\PerformanceScore;
use App\Models\Metric;
use App\Models\Slip;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $month    = Carbon::today()->format('Y-m');
        // Custom dates if provided (global or per-tile)
        $dateFrom = $request->tile_from ?? $request->date_from;
        $dateTo   = $request->tile_to   ?? $request->date_to;
        $period   = $request->period    ?? 'this_month';

        if (!$dateFrom || !$dateTo) {
            [$dateFrom, $dateTo] = $this->resolvePeriod($period);
        }

        $targetUser = $authUser;
        if ($request->staff_id && $authUser->hasAnyPermission(['approve slips', 'configure metrics'])) {
            $staff = User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))->find($request->staff_id);
            if ($staff) $targetUser = $staff;
        }

        // --- Performance Scores (Matching the Period if possible) ---
        // If it's a standard 10/20/30 day period, we pull from performance_scores table
        $scorePeriodType = in_array($period, ['10_days', '20_days', '30_days']) ? $period : '30_days';
        
        $metricscores = PerformanceScore::with('metric')
            ->where('user_id', $targetUser->id)
            ->where('period_type', $scorePeriodType)
            ->get();

        // --- Role Metrics with Aggregate Values ---
        $roleMetrics = Metric::where('is_active', true)
            ->whereHas('roles', fn($q) => $q->whereIn('roles.id', $targetUser->roles->pluck('id')))
            ->get()
            ->map(function($m) use ($targetUser, $dateFrom, $dateTo) {
                // Calculate aggregate value for this period
                $sum = Slip::where('user_id', $targetUser->id)
                    ->where('metric_id', $m->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'approved')
                    ->sum('value');
                
                $m->total_value = $sum;
                return $m;
            });

        $summary = SummaryReport::where('user_id', $targetUser->id)
            ->where('year_month', $month)
            ->first();

        $slipReport = Slip::with('metric')
            ->where('user_id', $targetUser->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $teamStats = null;
        if ($authUser->hasAnyPermission(['approve slips', 'configure metrics'])) {
            $teamStats = [
                'total_users'     => User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))->count(),
                'pending_slips'   => Slip::where('status', 'pending')->count(),
                'avg_total_score' => SummaryReport::where('year_month', $month)->avg('total_mark') ?? 0,
                'allStaff'        => User::whereHas('roles', fn($q) => $q->where('name', '!=', 'admin'))->select('id', 'name')->get(),
                'selectedStaffId' => $request->staff_id ?? '',
                'targetName'      => ($targetUser->id !== $authUser->id) ? $targetUser->name : 'Me',
            ];
        }

        return Inertia::render('Dashboard', [
            'summary'       => $summary,
            'metricscores'  => $metricscores,
            'roleMetrics'   => $roleMetrics,
            'month'         => Carbon::today()->format('F Y'),
            'period'        => $period,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'slipReport'    => $slipReport,
            'teamStats'     => $teamStats,
            'showDetailsId' => $request->show_details,
        ]);
    }

    private function resolvePeriod(string $period): array
    {
        $today = Carbon::today();
        return match($period) {
            '10_days'     => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->startOfMonth()->addDays(9)->toDateString()],
            '20_days'     => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->startOfMonth()->addDays(19)->toDateString()],
            '30_days'     => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->startOfMonth()->addDays(29)->toDateString()],
            'this_week'   => [$today->copy()->startOfWeek()->toDateString(), $today->toDateString()],
            '3_months'    => [$today->copy()->subMonths(3)->toDateString(), $today->toDateString()],
            '6_months'    => [$today->copy()->subMonths(6)->toDateString(), $today->toDateString()],
            '9_months'    => [$today->copy()->subMonths(9)->toDateString(), $today->toDateString()],
            '1_year'      => [$today->copy()->subYear()->toDateString(), $today->toDateString()],
            default       => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()], // this_month
        };
    }
}
