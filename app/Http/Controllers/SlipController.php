<?php

namespace App\Http\Controllers;

use App\Models\Slip;
use App\Models\Metric;
use App\Models\DailyScoringTier;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class SlipController extends Controller
{
    /**
     * Show the tabbed slip entry form + report section.
     */
    public function index(Request $request)
    {
        $user    = auth()->user();
        $roleIds = $user->roles->pluck('id');

        // Active metrics for this role (excluding automated ones like 'late')
        $metrics = Metric::where('is_active', true)
            ->where('key', '!=', 'late')
            ->whereHas('roles', fn($q) => $q->whereIn('roles.id', $roleIds))
            ->with('dailyScoringTiers')
            ->get();

        // Today + yesterday slips (for entry form lock/edit)
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        $existingSlips = Slip::where('user_id', $user->id)
            ->whereIn('date', [$today->toDateString(), $yesterday->toDateString()])
            ->get()
            ->keyBy(fn($s) => $s->metric_id . '_' . $s->date);

        // Report section: date range filter
        $reportFrom = $request->report_from ?? $today->copy()->subDays(7)->toDateString();
        $reportTo   = $request->report_to   ?? $today->toDateString();

        $reportSlips = Slip::with('metric')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$reportFrom, $reportTo])
            ->orderBy('date')
            ->get();

        // Group by date → pivot by metric key
        $reportRows = $reportSlips->groupBy('date')->map(function ($daySlips) use ($metrics) {
            $row = ['date' => $daySlips->first()->date, 'total_points' => 0];
            foreach ($metrics as $m) {
                $slip = $daySlips->firstWhere('metric_id', $m->id);
                $row[$m->key]          = $slip?->value ?? null;
                $row[$m->key . '_pts'] = $slip?->daily_points_earned ?? 0;
                $row[$m->key . '_status'] = $slip?->status ?? 'none';
                $row['total_points']  += ($slip?->daily_points_earned ?? 0);
            }
            return $row;
        })->values();

        return Inertia::render('Slips/Index', [
            'metrics'       => $metrics,
            'existingSlips' => $existingSlips->values(),
            'today'         => $today->toDateString(),
            'yesterday'     => $yesterday->toDateString(),
            'reportRows'    => $reportRows,
            'reportFrom'    => $reportFrom,
            'reportTo'      => $reportTo,
        ]);
    }


    /**
     * Calculate points preview in real-time via AJAX (no DB write).
     */
    public function previewPoints(Request $request)
    {
        $request->validate(['metric_id' => 'required|exists:metrics,id', 'value' => 'required|numeric|min:0']);

        $tiers = DailyScoringTier::where('metric_id', $request->metric_id)
            ->orderBy('min_value', 'desc')
            ->get();

        $points = 0;
        foreach ($tiers as $tier) {
            if ($request->value >= $tier->min_value) {
                $points = $tier->daily_points;
                break;
            }
        }

        return response()->json(['points' => $points]);
    }

    /**
     * Store or update a slip entry with all guards applied.
     */
    public function store(Request $request)
    {
        $request->validate([
            'metric_id' => 'required|exists:metrics,id',
            'date'      => 'required|date',
            'value'     => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        $date = Carbon::parse($request->date);
        $today = Carbon::today();
        $now   = Carbon::now();

        // --- Deadline Guard ---
        // Entries for 'yesterday' must be submitted before 12:00 PM today.
        if ($date->isYesterday() && $now->hour >= 12) {
            return back()->withErrors(['date' => 'Deadline passed. Yesterday\'s entry must be submitted before 12:00 PM today.']);
        }

        // Only today and yesterday are allowed
        if (!$date->isToday() && !$date->isYesterday()) {
            return back()->withErrors(['date' => 'Only today\'s or yesterday\'s date is allowed.']);
        }

        // --- Holiday Guard ---
        $isHoliday = Holiday::where('is_active', true)
            ->where('date', $date->toDateString())
            ->where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->exists();

        if ($isHoliday) {
            return back()->withErrors(['date' => 'This date is marked as a holiday or leave. No submission allowed.']);
        }

        // --- Approval Lock Guard ---
        $existing = Slip::where('user_id', $user->id)
            ->where('metric_id', $request->metric_id)
            ->where('date', $date->toDateString())
            ->first();

        if ($existing && $existing->status === 'approved') {
            return back()->withErrors(['metric_id' => 'This entry has been approved and cannot be changed.']);
        }

        // --- Calculate Daily Points ---
        $metric = Metric::find($request->metric_id);
        $userRole = $user->roles()->first();
        
        $comparisonValue = $request->value;
        
        if ($metric->value_type === 'percentage' && $metric->reference_metric_id) {
            $referenceSlip = Slip::where('user_id', $user->id)
                ->where('metric_id', $metric->reference_metric_id)
                ->where('date', $date->toDateString())
                ->where('status', 'approved')
                ->first();
            
            if ($referenceSlip && $referenceSlip->value > 0) {
                $comparisonValue = ($request->value / $referenceSlip->value) * 100;
            } else {
                $comparisonValue = 0;
            }
        }

        $sortOrder = ($metric->comparison_type === 'lte') ? 'asc' : 'desc';

        $tiers = DailyScoringTier::where('metric_id', $request->metric_id)
            ->when($userRole, function($q) use ($userRole) {
                return $q->where('role_id', $userRole->id);
            })
            ->orderBy('min_value', $sortOrder)
            ->get();

        $dailyPoints = 0;
        foreach ($tiers as $tier) {
            if ($metric->comparison_type === 'lte') {
                if ($comparisonValue <= $tier->min_value) {
                    $dailyPoints = $tier->daily_points;
                    break;
                }
            } else {
                if ($comparisonValue >= $tier->min_value) {
                    $dailyPoints = $tier->daily_points;
                    break;
                }
            }
        }

        // --- Upsert Slip ---
        Slip::updateOrCreate(
            ['user_id' => $user->id, 'metric_id' => $request->metric_id, 'date' => $date->toDateString()],
            ['value' => $request->value, 'daily_points_earned' => $dailyPoints, 'status' => 'pending']
        );

        return back()->with('success', 'Slip saved successfully. Pending approval.');
    }

    public function create() {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
