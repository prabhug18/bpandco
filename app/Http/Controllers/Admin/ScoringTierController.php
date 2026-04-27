<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScoringTierController extends Controller
{
    public function index()
    {
        $roles = \Spatie\Permission\Models\Role::where('name', '!=', 'admin')->orderBy('name')->get();
        $metrics = Metric::with(['dailyScoringTiers', 'periodTargets'])->orderBy('id')->get();
        return Inertia::render('Admin/ScoringTiers/Index', [
            'roles' => $roles,
            'metrics' => $metrics
        ]);
    }

    public function storeDaily(Request $request, Metric $metric)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'min_value' => 'required|numeric',
            'daily_points' => 'required|numeric',
            'tier_label' => 'nullable|string|in:green,yellow,red,grey'
        ]);

        $metric->dailyScoringTiers()->create([
            'role_id' => $request->role_id,
            'min_value' => $request->min_value,
            'daily_points' => $request->daily_points,
            'tier_label' => $request->tier_label ?? 'grey',
            'updated_by' => auth()->id()
        ]);

        return back()->with('success', 'Daily tier added.');
    }

    public function destroyDaily(DailyScoringTier $dailyTier)
    {
        $dailyTier->delete();
        return back()->with('success', 'Daily tier removed.');
    }

    public function storePeriod(Request $request, Metric $metric)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'period_type' => 'required|string', // 10_days, 20_days, 30_days, monthly
            'tier_label' => 'required|in:green,yellow,red,grey',
            'min_value' => 'required|numeric',
            'points_awarded' => 'required|numeric'
        ]);

        $metric->periodTargets()->create([
            'role_id' => $request->role_id,
            'period_type' => $request->period_type,
            'tier_label' => $request->tier_label,
            'min_value' => $request->min_value,
            'points_awarded' => $request->points_awarded,
            'updated_by' => auth()->id()
        ]);

        return back()->with('success', 'Period target added.');
    }

    public function destroyPeriod(PeriodTarget $periodTarget)
    {
        $periodTarget->delete();
        return back()->with('success', 'Period target removed.');
    }
}
