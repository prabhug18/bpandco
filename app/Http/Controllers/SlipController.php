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
            'isProduction'  => $user->roles->contains('name', 'Production'),
        ]);
    }


    public function previewPoints(Request $request)
    {
        $user = auth()->user();
        $userRole = $user ? $user->roles()->first() : null;
        $date = $request->date ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();

        // Check if this is a Production multi-field preview
        if ($request->has('production_multi') || ($request->has('boxes') && ($request->has('nc_thinner_ltr') || $request->has('enamel_thinner_ltr')))) {
            $boxMetric = Metric::where('key', 'production')->first();
            $mixingMetric = Metric::where('key', 'mixing_thinners')->first();

            $boxPoints = 0;
            if ($boxMetric && $request->filled('boxes')) {
                $boxVal = floatval($request->boxes);
                $boxTiers = DailyScoringTier::where('metric_id', $boxMetric->id)
                    ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                    ->orderBy('min_value', 'desc')
                    ->get();
                foreach ($boxTiers as $tier) {
                    if ($boxVal >= (float)$tier->min_value) {
                        $boxPoints = (float)$tier->daily_points;
                        break;
                    }
                }
            }

            $thinnerPoints = 0;
            $ncVal = floatval($request->nc_thinner_ltr ?? 0);
            $enamelVal = floatval($request->enamel_thinner_ltr ?? 0);
            $totalThinners = $ncVal + $enamelVal;

            if ($mixingMetric && $totalThinners > 0) {
                $mixingTiers = DailyScoringTier::where('metric_id', $mixingMetric->id)
                    ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                    ->orderBy('min_value', 'desc')
                    ->get();
                foreach ($mixingTiers as $tier) {
                    if ($totalThinners >= (float)$tier->min_value) {
                        $thinnerPoints = (float)$tier->daily_points;
                        break;
                    }
                }
            }

            return response()->json([
                'box_points'     => $boxPoints,
                'thinner_points' => $thinnerPoints,
                'total_thinners' => $totalThinners,
                'total_points'   => round($boxPoints + $thinnerPoints, 2),
            ]);
        }

        $request->validate([
            'metric_id' => 'required|exists:metrics,id', 
            'value'     => 'required|numeric|min:0',
            'date'      => 'nullable|date'
        ]);

        $metric = Metric::find($request->metric_id);

        if (!$metric) {
            return response()->json(['points' => 0]);
        }

        $comparisonValue = $request->value;

        // Handle Percentage Metrics
        if ($metric->value_type === 'percentage' && $metric->reference_metric_id) {
            $referenceSlip = Slip::where('user_id', $user->id)
                ->where('metric_id', $metric->reference_metric_id)
                ->where('date', $date)
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

        $points = 0;
        foreach ($tiers as $tier) {
            if ($metric->comparison_type === 'lte') {
                if ($comparisonValue <= $tier->min_value) {
                    $points = $tier->daily_points;
                    break;
                }
            } else {
                if ($comparisonValue >= $tier->min_value) {
                    $points = $tier->daily_points;
                    break;
                }
            }
        }

        return response()->json(['points' => $points]);
    }

    /**
     * Store or update a slip entry with all guards applied.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $date = Carbon::parse($request->date);
        $today = Carbon::today();
        $now   = Carbon::now();

        // --- Deadline Guard ---
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

        // --- Branch A: Production Multi-Field Submission ---
        if ($request->has('production_multi')) {
            $request->validate([
                'date'               => 'required|date',
                'boxes'              => 'nullable|numeric|min:0',
                'nc_thinner_ltr'     => 'nullable|numeric|min:0',
                'enamel_thinner_ltr' => 'nullable|numeric|min:0',
            ]);

            $boxMetric    = Metric::firstOrCreate(['key' => 'production'], ['label' => 'Box Production', 'unit' => 'Boxes', 'value_type' => 'absolute', 'scoring_type' => '10_20_30_days', 'comparison_type' => 'gte', 'is_active' => true]);
            $ncMetric     = Metric::firstOrCreate(['key' => 'nc_thinner_mixing'], ['label' => 'NC Thinner Mixing', 'unit' => 'Ltr', 'value_type' => 'absolute', 'scoring_type' => '10_20_30_days', 'comparison_type' => 'gte', 'is_active' => true]);
            $enamelMetric = Metric::firstOrCreate(['key' => 'enamel_thinner_mixing'], ['label' => 'Enamel Thinner Mixing', 'unit' => 'Ltr', 'value_type' => 'absolute', 'scoring_type' => '10_20_30_days', 'comparison_type' => 'gte', 'is_active' => true]);
            $mixingMetric = Metric::firstOrCreate(['key' => 'mixing_thinners'], ['label' => 'Mixing Thinners', 'unit' => 'Ltr', 'value_type' => 'absolute', 'scoring_type' => '10_20_30_days', 'comparison_type' => 'gte', 'is_active' => true]);

            $userRole = $user->roles()->first();

            // 1. Process Box Production
            if ($request->filled('boxes')) {
                $boxVal = floatval($request->boxes);
                $existingBox = Slip::where('user_id', $user->id)->where('metric_id', $boxMetric->id)->where('date', $date->toDateString())->first();
                if (!$existingBox || $existingBox->status !== 'approved') {
                    $boxPoints = 0;
                    $boxTiers = DailyScoringTier::where('metric_id', $boxMetric->id)
                        ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                        ->orderBy('min_value', 'desc')
                        ->get();
                    foreach ($boxTiers as $t) {
                        if ($boxVal >= (float)$t->min_value) {
                            $boxPoints = (float)$t->daily_points;
                            break;
                        }
                    }
                    Slip::updateOrCreate(
                        ['user_id' => $user->id, 'metric_id' => $boxMetric->id, 'date' => $date->toDateString()],
                        ['value' => $boxVal, 'daily_points_earned' => $boxPoints, 'status' => 'pending']
                    );
                }
            }

            // 2. Process NC & Enamel Thinner Mixing
            $ncVal     = floatval($request->nc_thinner_ltr ?? 0);
            $enamelVal = floatval($request->enamel_thinner_ltr ?? 0);
            $totalThinners = $ncVal + $enamelVal;

            if ($request->filled('nc_thinner_ltr')) {
                $existingNC = Slip::where('user_id', $user->id)->where('metric_id', $ncMetric->id)->where('date', $date->toDateString())->first();
                if (!$existingNC || $existingNC->status !== 'approved') {
                    Slip::updateOrCreate(
                        ['user_id' => $user->id, 'metric_id' => $ncMetric->id, 'date' => $date->toDateString()],
                        ['value' => $ncVal, 'daily_points_earned' => 0, 'status' => 'pending']
                    );
                }
            }

            if ($request->filled('enamel_thinner_ltr')) {
                $existingEnamel = Slip::where('user_id', $user->id)->where('metric_id', $enamelMetric->id)->where('date', $date->toDateString())->first();
                if (!$existingEnamel || $existingEnamel->status !== 'approved') {
                    Slip::updateOrCreate(
                        ['user_id' => $user->id, 'metric_id' => $enamelMetric->id, 'date' => $date->toDateString()],
                        ['value' => $enamelVal, 'daily_points_earned' => 0, 'status' => 'pending']
                    );
                }
            }

            // 3. Process Aggregate Mixing Thinners
            if ($request->filled('nc_thinner_ltr') || $request->filled('enamel_thinner_ltr')) {
                $existingMixing = Slip::where('user_id', $user->id)->where('metric_id', $mixingMetric->id)->where('date', $date->toDateString())->first();
                if (!$existingMixing || $existingMixing->status !== 'approved') {
                    $thinnerPoints = 0;
                    $mixingTiers = DailyScoringTier::where('metric_id', $mixingMetric->id)
                        ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                        ->orderBy('min_value', 'desc')
                        ->get();
                    foreach ($mixingTiers as $t) {
                        if ($totalThinners >= (float)$t->min_value) {
                            $thinnerPoints = (float)$t->daily_points;
                            break;
                        }
                    }
                    Slip::updateOrCreate(
                        ['user_id' => $user->id, 'metric_id' => $mixingMetric->id, 'date' => $date->toDateString()],
                        ['value' => $totalThinners, 'daily_points_earned' => $thinnerPoints, 'status' => 'pending']
                    );
                }
            }

            return back()->with('success', 'Production slips saved successfully. Pending approval.');
        }

        // --- Branch B: Standard Single Slip Submission ---
        $request->validate([
            'metric_id' => 'required|exists:metrics,id',
            'date'      => 'required|date',
            'value'     => 'required|numeric|min:0',
        ]);

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

        // If this is NC or Enamel thinner, keep aggregated mixing_thinners in sync
        if (in_array($metric->key, ['nc_thinner_mixing', 'enamel_thinner_mixing'])) {
            $otherKey = ($metric->key === 'nc_thinner_mixing') ? 'enamel_thinner_mixing' : 'nc_thinner_mixing';
            $otherMetric = Metric::where('key', $otherKey)->first();
            $otherVal = 0;
            if ($otherMetric) {
                $otherSlip = Slip::where('user_id', $user->id)->where('metric_id', $otherMetric->id)->where('date', $date->toDateString())->first();
                $otherVal = floatval($otherSlip?->value ?? 0);
            }
            $totMixing = floatval($request->value) + $otherVal;
            $mixMetric = Metric::where('key', 'mixing_thinners')->first();
            if ($mixMetric) {
                $mPoints = 0;
                $mTiers = DailyScoringTier::where('metric_id', $mixMetric->id)
                    ->when($userRole, fn($q) => $q->where('role_id', $userRole->id))
                    ->orderBy('min_value', 'desc')
                    ->get();
                foreach ($mTiers as $t) {
                    if ($totMixing >= (float)$t->min_value) {
                        $mPoints = (float)$t->daily_points;
                        break;
                    }
                }
                Slip::updateOrCreate(
                    ['user_id' => $user->id, 'metric_id' => $mixMetric->id, 'date' => $date->toDateString()],
                    ['value' => $totMixing, 'daily_points_earned' => $mPoints, 'status' => 'pending']
                );
            }
        }

        return back()->with('success', 'Slip saved successfully. Pending approval.');
    }

    public function create() {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
