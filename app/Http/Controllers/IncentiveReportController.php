<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIncentive;
use App\Models\EmployeeIncrement;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class IncentiveReportController extends Controller
{
    /**
     * Monthly incentive payables report
     */
    public function incentives(Request $request)
    {
        $month = $request->month ?? Carbon::today()->format('Y-m');

        $records = EmployeeIncentive::with('user')
            ->where('year_month', $month)
            ->orderBy('incentive_amount', 'desc')
            ->get();

        return Inertia::render('Reports/Incentives', [
            'records' => $records,
            'month'   => $month,
        ]);
    }

    /**
     * Mark an incentive as paid
     */
    public function markPaid(EmployeeIncentive $incentive)
    {
        $incentive->update(['status' => 'paid']);
        return back()->with('success', 'Incentive marked as paid.');
    }

    /**
     * Annual increments report
     */
    public function increments(Request $request)
    {
        $year = $request->year ?? Carbon::today()->year;

        $records = EmployeeIncrement::with('user')
            ->where('year', $year)
            ->orderBy('increment_percentage', 'desc')
            ->get();

        return Inertia::render('Reports/Increments', [
            'records' => $records,
            'year'    => $year,
        ]);
    }
}
