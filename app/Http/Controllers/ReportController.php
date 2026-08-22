<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\Attendance;
use App\Models\PerformanceScore;
use App\Models\PeriodTarget;
use App\Models\SummaryReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

        // Build Growth Plan
        $growthPlan = $this->buildGrowthPlan($user, $metrics, $scores, $monthStr);

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
            'growthPlan'   => $growthPlan,
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
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd   = $month->copy()->endOfMonth()->toDateString();

        // Get non-admin & non-supervisor employees with their roles
        $users = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']))
            ->get();

        // Get all metrics with assigned roles
        $metrics = Metric::with('roles')->get();

        // Get performance score aggregates for the selected period type
        $scores = PerformanceScore::where('period_type', $period)
            ->whereBetween('period_start', [$monthStart, $monthEnd])
            ->get();

        // Get approved slips totals for raw values
        $slipsSummary = Slip::whereBetween('date', [$monthStart, $monthEnd])
            ->where('status', 'approved')
            ->select('user_id', 'metric_id', \Illuminate\Support\Facades\DB::raw('SUM(value) as total_value'), \Illuminate\Support\Facades\DB::raw('SUM(daily_points_earned) as total_daily_points'))
            ->groupBy('user_id', 'metric_id')
            ->get()
            ->groupBy('user_id');

        // Group users by their primary role
        $roleGroups = [];
        foreach ($users as $user) {
            $roleName = $user->roles->first()?->name ?? 'General Staff';
            $roleId   = $user->roles->first()?->id;

            if (!isset($roleGroups[$roleName])) {
                // Find metrics applicable to this role
                $applicableMetrics = $metrics->filter(function($m) use ($roleId) {
                    if (!$roleId) return true;
                    return $m->roles->isEmpty() || $m->roles->contains('id', $roleId);
                })->values();

                $roleGroups[$roleName] = [
                    'role'    => $roleName,
                    'metrics' => $applicableMetrics,
                    'members' => [],
                ];
            }

            // Build employee metric matrix
            $userSlips = $slipsSummary->get($user->id, collect());
            $userScores = $scores->where('user_id', $user->id);
            $totalMark = 0;

            $metricData = [];
            foreach ($roleGroups[$roleName]['metrics'] as $metric) {
                $scoreRecord = $userScores->firstWhere('metric_id', $metric->id);
                $slipRecord  = $userSlips->firstWhere('metric_id', $metric->id);

                $rawValue = $slipRecord ? floatval($slipRecord->total_value) : 0;
                $points = $scoreRecord ? floatval($scoreRecord->period_points_earned) : ($slipRecord ? floatval($slipRecord->total_daily_points) : 0);
                $light  = $scoreRecord ? $scoreRecord->traffic_light : ($rawValue > 0 ? 'green' : 'grey');

                $totalMark += $points;

                $metricData[$metric->id] = [
                    'rawValue'       => $rawValue,
                    'formattedValue' => $this->formatValue($rawValue, $metric),
                    'points'         => $points,
                    'light'          => $light, // green, yellow, red, grey
                ];
            }

            $roleGroups[$roleName]['members'][] = [
                'id'         => $user->id,
                'name'       => $user->name,
                'metricData' => $metricData,
                'totalMark'  => round($totalMark, 2),
            ];
        }

        return Inertia::render('Reports/Team', [
            'roleGroups' => array_values($roleGroups),
            'month'      => $month->format('Y-m'),
            'period'     => $period,
        ]);
    }

    /**
     * Greenscore Leaderboard / Hall of Fame Report
     */
    public function greenscore(Request $request)
    {
        $month = $request->month ? Carbon::parse($request->month) : Carbon::today();
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd   = $month->copy()->endOfMonth()->toDateString();

        $metrics = Metric::all();
        $leaderboard = [];
        $sno = 1;

        foreach ($metrics as $metric) {
            // Find top performer for this metric in the month (excluding admin and supervisor)
            $topSlip = Slip::with('user')
                ->whereHas('user.roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']))
                ->where('metric_id', $metric->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('status', 'approved')
                ->select('user_id', \Illuminate\Support\Facades\DB::raw('SUM(value) as total_value'))
                ->groupBy('user_id')
                ->orderByDesc('total_value')
                ->first();

            if ($topSlip && $topSlip->user && $topSlip->total_value > 0) {
                $resultLabel = 'Highest ' . ($metric->label ?? $metric->name);
                
                // Map common label aliases to match image
                $nameLower = strtolower($metric->label ?? $metric->name);
                if (str_contains($nameLower, 'sale')) $resultLabel = 'Highest Sales';
                elseif (str_contains($nameLower, 'collection')) $resultLabel = 'Highest Collections';
                elseif (str_contains($nameLower, 'colour') || str_contains($nameLower, 'color')) $resultLabel = 'Highest Colour Matching';
                elseif (str_contains($nameLower, 'customer')) $resultLabel = 'Highest Customer Handling';

                $leaderboard[] = [
                    'sno'       => $sno++,
                    'name'      => strtoupper($topSlip->user->name),
                    'raw_data'  => floatval($topSlip->total_value),
                    'data'      => $this->formatValue(floatval($topSlip->total_value), $metric),
                    'results'   => $resultLabel,
                ];
            }
        }

        // Add Top Attendance Achiever (excluding admin and supervisor)
        $topAttendance = Attendance::with('user')
            ->whereHas('user.roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']))
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('status', 'present')
            ->select('user_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_days'))
            ->groupBy('user_id')
            ->orderByDesc('total_days')
            ->first();

        if ($topAttendance && $topAttendance->user && $topAttendance->total_days > 0) {
            $leaderboard[] = [
                'sno'      => $sno++,
                'name'     => strtoupper($topAttendance->user->name),
                'raw_data' => $topAttendance->total_days,
                'data'     => $topAttendance->total_days . ' Days',
                'results'  => 'Highest On Attendance',
            ];
        }

        return Inertia::render('Reports/Greenscore', [
            'leaderboard' => $leaderboard,
            'month'       => $month->format('Y-m'),
            'monthName'   => strtoupper($month->format('F Y')),
        ]);
    }

    /**
     * Employee Attendance Performance Report (Month-wise & Year-wise)
     */
    public function attendance(Request $request)
    {
        $mode = $request->view_mode ?? 'month'; // 'month' or 'year'
        $selectedRole = $request->role_id ?? null;

        $monthStr = $request->month ? Carbon::parse($request->month)->format('Y-m') : Carbon::today()->format('Y-m');
        $yearStr  = $request->year ?? Carbon::parse($monthStr)->format('Y');

        // Query non-admin employees (or filter by role / user)
        $userQuery = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']));

        if ($request->user_id) {
            $userQuery->where('id', $request->user_id);
        }

        if ($selectedRole) {
            $userQuery->whereHas('roles', fn($q) => $q->where('roles.id', $selectedRole));
        }

        $employees = $userQuery->orderBy('name')->get();

        $allRoles = \Spatie\Permission\Models\Role::whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor'])->get();
        $allEmployeesList = User::whereHas('roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $attendanceMetric = Metric::where('key', 'attendance')->first();
        $lateMetric       = Metric::where('key', 'late')->first();
        $attMetricId  = $attendanceMetric?->id ?? 5;
        $lateMetricId = $lateMetric?->id ?? 7;

        if ($mode === 'month') {
            $monthCarbon = Carbon::parse($monthStr . '-01');
            $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
            $endOfMonth   = $monthCarbon->copy()->endOfMonth()->toDateString();
            $daysInMonth  = $monthCarbon->daysInMonth;

            // Fetch attendance records for the month
            $records = Attendance::whereBetween('date', [$startOfMonth, $endOfMonth])
                ->get()
                ->groupBy('user_id');

            // Pre-fetch PerformanceScore and Slips for the month
            $scoresMap = PerformanceScore::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->whereBetween('period_start', [$startOfMonth, $endOfMonth])
                ->get()
                ->groupBy('user_id');

            $slipsMap = Slip::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->where('status', 'approved')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->get()
                ->groupBy('user_id');

            $monthData = [];
            foreach ($employees as $emp) {
                $userRecords = $records->get($emp->id, collect())->keyBy('date');
                $userRoleId  = $emp->roles->first()?->id;
                
                $dailyGrid = [];
                $presentCount = 0;
                $lateCount = 0;
                $halfDayCount = 0;
                $absentCount = 0;
                $holidayCount = 0;

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayDate = $monthCarbon->copy()->day($d)->toDateString();
                    $rec = $userRecords->get($dayDate);

                    $statusCode = '-'; // empty / pending
                    if ($rec) {
                        $st = strtolower($rec->status);
                        if ($st === 'present') { $statusCode = 'P'; $presentCount++; }
                        elseif ($st === 'late') { $statusCode = 'L'; $lateCount++; }
                        elseif ($st === 'half_day') { $statusCode = 'HD'; $halfDayCount++; }
                        elseif ($st === 'absent') { $statusCode = 'A'; $absentCount++; }
                        elseif ($st === 'holiday') { $statusCode = 'H'; $holidayCount++; }
                        else { $statusCode = 'P'; $presentCount++; }
                    }

                    $dailyGrid[$d] = [
                        'date'   => $dayDate,
                        'status' => $statusCode,
                        'points' => $rec ? floatval($rec->daily_points_earned) : 0,
                    ];
                }

                // Points calculation logic
                $totalPoints = 0;
                $empScores = $scoresMap->get($emp->id, collect());
                $scorePts  = $empScores->sum('period_points_earned');

                if ($scorePts > 0) {
                    $totalPoints = $scorePts;
                } else {
                    $empSlips = $slipsMap->get($emp->id, collect());
                    $slipPts  = $empSlips->sum('daily_points_earned');

                    if ($slipPts > 0) {
                        $totalPoints = $slipPts;
                    } elseif ($presentCount > 0) {
                        // Calculate via PeriodTarget matching presentCount
                        $target = PeriodTarget::where('metric_id', $attMetricId)
                            ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                            ->where('min_value', '<=', $presentCount)
                            ->orderBy('min_value', 'desc')
                            ->first();

                        if ($target) {
                            $totalPoints = floatval($target->points_awarded);
                        } else {
                            if ($presentCount >= 25) $totalPoints = 10;
                            elseif ($presentCount >= 20) $totalPoints = 7;
                            elseif ($presentCount >= 15) $totalPoints = 5;
                        }
                    }
                }

                $monthData[] = [
                    'id'           => $emp->id,
                    'name'         => $emp->name,
                    'role'         => $emp->roles->first()?->name ?? 'Staff',
                    'dailyGrid'    => $dailyGrid,
                    'presentCount' => $presentCount,
                    'lateCount'    => $lateCount,
                    'halfDayCount' => $halfDayCount,
                    'absentCount'  => $absentCount,
                    'holidayCount' => $holidayCount,
                    'totalPoints'  => round($totalPoints, 2),
                ];
            }

            return Inertia::render('Reports/Attendance', [
                'viewMode'         => 'month',
                'month'            => $monthStr,
                'year'             => $yearStr,
                'daysInMonth'      => $daysInMonth,
                'reportData'       => $monthData,
                'allRoles'         => $allRoles,
                'allEmployeesList' => $allEmployeesList,
                'selectedUserId'   => $request->user_id,
                'selectedRoleId'   => $selectedRole,
            ]);

        } else {
            // Year-wise mode
            $startOfYear = $yearStr . '-01-01';
            $endOfYear   = $yearStr . '-12-31';

            $records = Attendance::whereBetween('date', [$startOfYear, $endOfYear])
                ->get()
                ->groupBy('user_id');

            $scoresMap = PerformanceScore::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->whereBetween('period_start', [$startOfYear, $endOfYear])
                ->get()
                ->groupBy('user_id');

            $slipsMap = Slip::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->where('status', 'approved')
                ->whereBetween('date', [$startOfYear, $endOfYear])
                ->get()
                ->groupBy('user_id');

            $yearData = [];
            foreach ($employees as $emp) {
                $userRecords = $records->get($emp->id, collect());
                $userRoleId  = $emp->roles->first()?->id;
                
                $monthlyGrid = [];
                $yearlyTotalPresent = 0;
                $yearlyTotalLate    = 0;
                $yearlyTotalPoints  = 0;

                for ($m = 1; $m <= 12; $m++) {
                    $mStr = sprintf('%02d', $m);
                    $monthCarbon = Carbon::parse("{$yearStr}-{$mStr}-01");
                    $mStart = $monthCarbon->copy()->startOfMonth()->toDateString();
                    $mEnd   = $monthCarbon->copy()->endOfMonth()->toDateString();

                    $mRecords = $userRecords->filter(fn($r) => $r->date >= $mStart && $r->date <= $mEnd);
                    $mPresent = $mRecords->filter(fn($r) => in_array(strtolower($r->status), ['present', 'late', 'half_day']))->count();
                    $mLate    = $mRecords->filter(fn($r) => strtolower($r->status) === 'late')->count();
                    
                    // Monthly points calculation
                    $mScores = $scoresMap->get($emp->id, collect())->filter(fn($s) => $s->period_start >= $mStart && $s->period_start <= $mEnd);
                    $mPoints = $mScores->sum('period_points_earned');

                    if ($mPoints <= 0) {
                        $mSlips = $slipsMap->get($emp->id, collect())->filter(fn($sl) => $sl->date >= $mStart && $sl->date <= $mEnd);
                        $mPtsFromSlips = $mSlips->sum('daily_points_earned');

                        if ($mPtsFromSlips > 0) {
                            $mPoints = $mPtsFromSlips;
                        } elseif ($mPresent > 0) {
                            $target = PeriodTarget::where('metric_id', $attMetricId)
                                ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                                ->where('min_value', '<=', $mPresent)
                                ->orderBy('min_value', 'desc')
                                ->first();

                            if ($target) {
                                $mPoints = floatval($target->points_awarded);
                            } else {
                                if ($mPresent >= 25) $mPoints = 10;
                                elseif ($mPresent >= 20) $mPoints = 7;
                                elseif ($mPresent >= 15) $mPoints = 5;
                            }
                        }
                    }

                    $yearlyTotalPresent += $mPresent;
                    $yearlyTotalLate    += $mLate;
                    $yearlyTotalPoints  += $mPoints;

                    $monthlyGrid[$m] = [
                        'monthName'    => $monthCarbon->format('M'),
                        'presentCount' => $mPresent,
                        'lateCount'    => $mLate,
                        'totalDays'    => $monthCarbon->daysInMonth,
                        'points'       => round($mPoints, 2),
                    ];
                }

                $yearData[] = [
                    'id'                 => $emp->id,
                    'name'               => $emp->name,
                    'role'               => $emp->roles->first()?->name ?? 'Staff',
                    'monthlyGrid'        => $monthlyGrid,
                    'yearlyTotalPresent' => $yearlyTotalPresent,
                    'yearlyTotalLate'    => $yearlyTotalLate,
                    'yearlyTotalPoints'  => round($yearlyTotalPoints, 2),
                ];
            }

            return Inertia::render('Reports/Attendance', [
                'viewMode'         => 'year',
                'month'            => $monthStr,
                'year'             => $yearStr,
                'reportData'       => $yearData,
                'allRoles'         => $allRoles,
                'allEmployeesList' => $allEmployeesList,
                'selectedUserId'   => $request->user_id,
                'selectedRoleId'   => $selectedRole,
            ]);
        }
    }

    /**
     * Export Employee Attendance Performance Report to Excel (.xlsx)
     */
    public function exportAttendanceExcel(Request $request)
    {
        $mode = $request->view_mode ?? 'month'; // 'month' or 'year'
        $selectedRole = $request->role_id ?? null;

        $monthStr = $request->month ? Carbon::parse($request->month)->format('Y-m') : Carbon::today()->format('Y-m');
        $yearStr  = $request->year ?? Carbon::parse($monthStr)->format('Y');

        $userQuery = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']));

        if ($request->user_id) {
            $userQuery->where('id', $request->user_id);
        }

        if ($selectedRole) {
            $userQuery->whereHas('roles', fn($q) => $q->where('roles.id', $selectedRole));
        }

        $employees = $userQuery->orderBy('name')->get();
        $attMetric = Metric::where('key', 'attendance')->first();
        $lateMetric = Metric::where('key', 'late')->first();
        $attMetricId  = $attMetric?->id ?? 5;
        $lateMetricId = $lateMetric?->id ?? 7;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Report');

        if ($mode === 'month') {
            $monthCarbon = Carbon::parse($monthStr . '-01');
            $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
            $endOfMonth   = $monthCarbon->copy()->endOfMonth()->toDateString();
            $daysInMonth  = $monthCarbon->daysInMonth;

            $records = Attendance::whereBetween('date', [$startOfMonth, $endOfMonth])
                ->get()
                ->groupBy('user_id');

            $scoresMap = PerformanceScore::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->whereBetween('period_start', [$startOfMonth, $endOfMonth])
                ->get()
                ->groupBy('user_id');

            $slipsMap = Slip::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->where('status', 'approved')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->get()
                ->groupBy('user_id');

            // Header Title
            $lastColLetter = Coordinate::stringFromColumnIndex($daysInMonth + 7);
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->setCellValue('A1', 'B.P.&CO - EMPLOYEE ATTENDANCE PERFORMANCE REPORT - ' . strtoupper($monthCarbon->format('F Y')));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('FF003287'));
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Table Headers
            $colIndex = 1;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'STAFF NAME');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'ROLE');

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', $d);
            }

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'P');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'L');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'HD');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'A');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . '3', 'POINTS');

            // Style Header Row (Row 3)
            $sheet->getStyle("A3:{$lastColLetter}3")->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
            $sheet->getStyle("A3:{$lastColLetter}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF003287');
            $sheet->getStyle("A3:{$lastColLetter}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowIndex = 4;
            foreach ($employees as $emp) {
                $userRecords = $records->get($emp->id, collect())->keyBy('date');
                $userRoleId  = $emp->roles->first()?->id;

                $presentCount = 0; $lateCount = 0; $halfDayCount = 0; $absentCount = 0;

                $c = 1;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, strtoupper($emp->name));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $emp->roles->first()?->name ?? 'Staff');

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayDate = $monthCarbon->copy()->day($d)->toDateString();
                    $rec = $userRecords->get($dayDate);

                    $statusCode = '-';
                    if ($rec) {
                        $st = strtolower($rec->status);
                        if ($st === 'present') { $statusCode = 'P'; $presentCount++; }
                        elseif ($st === 'late') { $statusCode = 'L'; $lateCount++; }
                        elseif ($st === 'half_day') { $statusCode = 'HD'; $halfDayCount++; }
                        elseif ($st === 'absent') { $statusCode = 'A'; $absentCount++; }
                        elseif ($st === 'holiday') { $statusCode = 'H'; }
                        else { $statusCode = 'P'; $presentCount++; }
                    }
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $statusCode);
                }

                // Points calculation
                $totalPoints = 0;
                $empScores = $scoresMap->get($emp->id, collect());
                $scorePts  = $empScores->sum('period_points_earned');

                if ($scorePts > 0) {
                    $totalPoints = $scorePts;
                } else {
                    $empSlips = $slipsMap->get($emp->id, collect());
                    $slipPts  = $empSlips->sum('daily_points_earned');
                    if ($slipPts > 0) {
                        $totalPoints = $slipPts;
                    } elseif ($presentCount > 0) {
                        $target = PeriodTarget::where('metric_id', $attMetricId)
                            ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                            ->where('min_value', '<=', $presentCount)
                            ->orderBy('min_value', 'desc')
                            ->first();

                        if ($target) {
                            $totalPoints = floatval($target->points_awarded);
                        } else {
                            if ($presentCount >= 25) $totalPoints = 10;
                            elseif ($presentCount >= 20) $totalPoints = 7;
                            elseif ($presentCount >= 15) $totalPoints = 5;
                        }
                    }
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $presentCount);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $lateCount);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $halfDayCount);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $absentCount);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, round($totalPoints, 2));

                $sheet->getStyle("A{$rowIndex}:{$lastColLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $rowIndex++;
            }

            $fileName = 'Attendance_Report_' . $monthStr . '.xlsx';

        } else {
            // Year-wise mode
            $startOfYear = $yearStr . '-01-01';
            $endOfYear   = $yearStr . '-12-31';

            $records = Attendance::whereBetween('date', [$startOfYear, $endOfYear])
                ->get()
                ->groupBy('user_id');

            $scoresMap = PerformanceScore::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->whereBetween('period_start', [$startOfYear, $endOfYear])
                ->get()
                ->groupBy('user_id');

            $slipsMap = Slip::whereIn('metric_id', [$attMetricId, $lateMetricId])
                ->where('status', 'approved')
                ->whereBetween('date', [$startOfYear, $endOfYear])
                ->get()
                ->groupBy('user_id');

            // Header Title
            $sheet->mergeCells("A1:Q1");
            $sheet->setCellValue('A1', 'B.P.&CO - EMPLOYEE ATTENDANCE PERFORMANCE REPORT - YEAR ' . $yearStr);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('FF003287'));
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Table Headers
            $colHeaders = ['STAFF NAME', 'ROLE', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC', 'TOTAL PRESENT', 'TOTAL LATE', 'ANNUAL SCORE'];
            foreach ($colHeaders as $idx => $hdr) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($idx + 1) . '3', $hdr);
            }

            $sheet->getStyle("A3:Q3")->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
            $sheet->getStyle("A3:Q3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF003287');
            $sheet->getStyle("A3:Q3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowIndex = 4;
            foreach ($employees as $emp) {
                $userRecords = $records->get($emp->id, collect());
                $userRoleId  = $emp->roles->first()?->id;

                $c = 1;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, strtoupper($emp->name));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $emp->roles->first()?->name ?? 'Staff');

                $yearlyTotalPresent = 0; $yearlyTotalLate = 0; $yearlyTotalPoints = 0;

                for ($m = 1; $m <= 12; $m++) {
                    $mStr = sprintf('%02d', $m);
                    $monthCarbon = Carbon::parse("{$yearStr}-{$mStr}-01");
                    $mStart = $monthCarbon->copy()->startOfMonth()->toDateString();
                    $mEnd   = $monthCarbon->copy()->endOfMonth()->toDateString();

                    $mRecords = $userRecords->filter(fn($r) => $r->date >= $mStart && $r->date <= $mEnd);
                    $mPresent = $mRecords->filter(fn($r) => in_array(strtolower($r->status), ['present', 'late', 'half_day']))->count();
                    $mLate    = $mRecords->filter(fn($r) => strtolower($r->status) === 'late')->count();

                    $mScores = $scoresMap->get($emp->id, collect())->filter(fn($s) => $s->period_start >= $mStart && $s->period_start <= $mEnd);
                    $mPoints = $mScores->sum('period_points_earned');

                    if ($mPoints <= 0) {
                        $mSlips = $slipsMap->get($emp->id, collect())->filter(fn($sl) => $sl->date >= $mStart && $sl->date <= $mEnd);
                        $mPtsFromSlips = $mSlips->sum('daily_points_earned');

                        if ($mPtsFromSlips > 0) {
                            $mPoints = $mPtsFromSlips;
                        } elseif ($mPresent > 0) {
                            $target = PeriodTarget::where('metric_id', $attMetricId)
                                ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                                ->where('min_value', '<=', $mPresent)
                                ->orderBy('min_value', 'desc')
                                ->first();

                            if ($target) {
                                $mPoints = floatval($target->points_awarded);
                            } else {
                                if ($mPresent >= 25) $mPoints = 10;
                                elseif ($mPresent >= 20) $mPoints = 7;
                                elseif ($mPresent >= 15) $mPoints = 5;
                            }
                        }
                    }

                    $yearlyTotalPresent += $mPresent;
                    $yearlyTotalLate    += $mLate;
                    $yearlyTotalPoints  += $mPoints;

                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, "{$mPresent}/{$monthCarbon->daysInMonth}");
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $yearlyTotalPresent);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, $yearlyTotalLate);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $rowIndex, round($yearlyTotalPoints, 2));

                $sheet->getStyle("A{$rowIndex}:Q{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $rowIndex++;
            }

            $fileName = 'Attendance_Report_Year_' . $yearStr . '.xlsx';
        }

        // Auto-size columns
        foreach ($sheet->getColumnIterator() as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }

        // Stream output
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Build Growth Plan advice for the employee.
     */
    private function buildGrowthPlan($user, $metrics, $scores, $monthStr): ?array
    {
        if (!$user || empty($metrics) || empty($scores)) {
            return null;
        }

        $month = \Carbon\Carbon::parse($monthStr);
        $dateFrom = $month->copy()->startOfMonth()->toDateString();
        $dateTo = $month->copy()->endOfMonth()->toDateString();

        $totalScore = 0;
        $adviceItems = [];

        // Tier order: green is best, grey is worst (for normal gte metrics)
        $tierOrder = ['green' => 4, 'yellow' => 3, 'red' => 2, 'grey' => 1];
        $tierEmoji = ['green' => '🟢', 'yellow' => '🟡', 'red' => '🔴', 'grey' => '⬜'];
        $tierColors = ['green' => '#28a745', 'yellow' => '#ffc107', 'red' => '#dc3545', 'grey' => '#6c757d'];

        foreach ($metrics as $metric) {
            // Find the 30_days score, fall back to monthly
            $score = $scores->first(fn($s) => $s->metric_id === $metric->id && $s->period_type === '30_days');
            if (!$score) {
                $score = $scores->first(fn($s) => $s->metric_id === $metric->id && $s->period_type === 'monthly');
            }

            $currentValue = $score ? floatval($score->cumulative_value) : 0;
            $earnedPoints = $score ? floatval($score->period_points_earned) : 0;
            $currentLight = $score->traffic_light ?? 'grey';
            $totalScore += $earnedPoints;

            // Get period targets (30_days or monthly) sorted by min_value desc
            $targets = $metric->periodTargets
                ->filter(fn($t) => in_array($t->period_type, ['30_days', 'monthly']))
                ->sortByDesc('min_value')
                ->values();

            if ($targets->isEmpty()) continue;

            $isInverse = $metric->comparison_type === 'lte'; // Late: lower is better
            $isPercentage = $metric->value_type === 'percentage';

            // Determine current tier rank
            $currentTierRank = $tierOrder[$currentLight] ?? 0;

            // Build tier breakdown
            $tiers = [];
            foreach ($targets as $target) {
                $tierLabel = $target->tier_label;
                $tierRank = $tierOrder[$tierLabel] ?? 0;
                $minVal = floatval($target->min_value);
                $pts = floatval($target->points_awarded);

                $tierInfo = [
                    'label' => $tierLabel,
                    'emoji' => $tierEmoji[$tierLabel] ?? '⬜',
                    'color' => $tierColors[$tierLabel] ?? '#6c757d',
                    'threshold' => $minVal,
                    'points' => $pts,
                    'gap' => null,
                    'gapText' => null,
                    'isCurrent' => $tierLabel === $currentLight,
                    'isAchieved' => $tierRank <= $currentTierRank,
                ];

                // Calculate gap to this tier
                if ($isInverse) {
                    // For inverse metrics: achieved if value <= threshold
                    $tierInfo['isAchieved'] = $currentValue <= $minVal;
                    if (!$tierInfo['isAchieved']) {
                        $gap = $currentValue - $minVal;
                        $tierInfo['gap'] = $gap;
                        $tierInfo['gapText'] = 'Reduce by ' . $this->formatValue($gap, $metric) . ' to reach';
                    }
                } else {
                    // For normal metrics: achieved if value >= threshold
                    if (str_contains(strtolower($metric->key), 'collection') || str_contains(strtolower($metric->label), 'collection')) {
                        $tierInfo['isAchieved'] = $currentValue >= $minVal;
                        if (!$tierInfo['isAchieved']) {
                            // Calculate dynamic pending collection amount
                            $refMetricId = $metric->reference_metric_id;
                            $refValue = 0;
                            if ($refMetricId) {
                                $refValue = \App\Models\Slip::where('user_id', $user->id)
                                    ->where('metric_id', $refMetricId)
                                    ->where('status', 'approved')
                                    ->whereBetween('date', [$dateFrom, $dateTo])
                                    ->sum('value') ?? 0;
                            }
                            $currentCollection = \App\Models\Slip::where('user_id', $user->id)
                                ->where('metric_id', $metric->id)
                                ->where('status', 'approved')
                                ->whereBetween('date', [$dateFrom, $dateTo])
                                ->sum('value') ?? 0;

                            $targetAmount = ($minVal / 100) * $refValue;
                            $gapAmount = $targetAmount - $currentCollection;

                            if ($gapAmount > 0) {
                                $tierInfo['gap'] = $gapAmount;
                                $tierInfo['gapText'] = 'Need ' . $this->formatValue($gapAmount, $metric) . ' more to reach';
                            } else {
                                $tierInfo['gap'] = 0;
                                $tierInfo['gapText'] = 'no pending collection to reach';
                            }
                        }
                    } elseif ($isPercentage) {
                        $tierInfo['isAchieved'] = $currentValue >= $minVal;
                        if (!$tierInfo['isAchieved']) {
                            $gap = $minVal - $currentValue;
                            $tierInfo['gap'] = $gap;
                            $tierInfo['gapText'] = 'Need ' . number_format($gap, 1) . '% more to reach';
                        }
                    } else {
                        $tierInfo['isAchieved'] = $currentValue >= $minVal;
                        if (!$tierInfo['isAchieved']) {
                            $gap = $minVal - $currentValue;
                            $tierInfo['gap'] = $gap;
                            $tierInfo['gapText'] = 'Need ' . $this->formatValue($gap, $metric) . ' more to reach';
                        }
                    }
                }

                $tiers[] = $tierInfo;
            }

            // Generate advice text
            $advice = '';
            if ($currentTierRank >= 4 || ($isInverse && $currentLight === 'green')) {
                $advice = '✅ On track! Maintaining Green tier.';
            } elseif ($isInverse) {
                $greenTarget = $targets->firstWhere('tier_label', 'green');
                if ($greenTarget) {
                    $advice = 'Keep at or below ' . $this->formatValue(floatval($greenTarget->min_value), $metric) . ' to maintain Green (' . floatval($greenTarget->points_awarded) . ' pts).';
                }
            } else {
                // Find the next tier up
                $nextTier = collect($tiers)
                    ->filter(fn($t) => !$t['isAchieved'] && $t['gap'] !== null)
                    ->sortBy('threshold')
                    ->first();
                if ($nextTier) {
                    $advice = $nextTier['gapText'] . ' ' . $nextTier['emoji'] . ' ' . ucfirst($nextTier['label']) . ' (' . $nextTier['points'] . ' pts).';
                }
            }

            $adviceItems[] = [
                'metricId' => $metric->id,
                'metricLabel' => $metric->label,
                'metricKey' => $metric->key,
                'unit' => $metric->unit,
                'isInverse' => $isInverse,
                'isPercentage' => $isPercentage,
                'currentValue' => $currentValue,
                'currentValueFormatted' => $this->formatValue($currentValue, $metric),
                'currentTier' => $currentLight,
                'earnedPoints' => $earnedPoints,
                'advice' => $advice,
                'tiers' => $tiers,
            ];
        }

        return [
            'employeeName' => $user->name,
            'month' => $monthStr,
            'totalScore' => round($totalScore, 2),
            'items' => $adviceItems,
        ];
    }

    /**
     * Format a metric value with proper units.
     */
    private function formatValue(float $value, $metric): string
    {
        $unit = strtolower($metric->unit ?? '');

        if (in_array($unit, ['amount', 'rupees', 'inr', '₹'])) {
            if ($value >= 100000) {
                return '₹' . number_format($value / 100000, 2) . 'L';
            } elseif ($value >= 1000) {
                return '₹' . number_format($value / 1000, 1) . 'K';
            }
            return '₹' . number_format($value, 0);
        }

        if (in_array($unit, ['percentage', '%'])) {
            return number_format($value, 1) . '%';
        }

        // For count, ltr, boxes, leaves, time, etc.
        if ($value == intval($value)) {
            return number_format($value, 0) . ' ' . $metric->unit;
        }
        return number_format($value, 1) . ' ' . $metric->unit;
    }
}
