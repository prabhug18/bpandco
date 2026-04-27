<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncentiveConfiguration;
use App\Models\IncrementConfiguration;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncentiveConfigController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Incentives/Index', [
            'incentives' => IncentiveConfiguration::with('role')->get(),
            'increments' => IncrementConfiguration::with('role')->get(),
            'roles'      => Role::where('name', '!=', 'admin')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'consecutive_green_months' => 'required|integer',
            'min_score'                => 'required|numeric',
            'incentive_amount'         => 'required|numeric',
            'role_id'                  => 'nullable|exists:roles,id',
        ]);

        IncentiveConfiguration::create($request->all());

        return back()->with('success', 'Incentive slab added.');
    }

    public function storeIncrement(Request $request)
    {
        $request->validate([
            'green_months_required' => 'required|integer',
            'increment_percentage'  => 'required|numeric',
            'role_id'               => 'nullable|exists:roles,id',
        ]);

        IncrementConfiguration::create($request->all());

        return back()->with('success', 'Increment slab added.');
    }

    public function destroy(string $id)
    {
        IncentiveConfiguration::findOrFail($id)->delete();
        return back()->with('success', 'Incentive slab removed.');
    }

    public function destroyIncrement(string $id)
    {
        IncrementConfiguration::findOrFail($id)->delete();
        return back()->with('success', 'Increment slab removed.');
    }
}

