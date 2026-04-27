<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MetricController extends Controller
{
    public function index()
    {
        // Load metrics and their assigned roles
        $metrics = Metric::all();
        // Load metrics with their roles, but since we haven't defined the relationship yet, let's just get the raw table data for now,
        // Actually, we should define the relationship on the Metric model first.
        // Assuming we do:
        $metrics = Metric::with('roles')->orderBy('id')->get();
        
        return Inertia::render('Admin/Metrics/Index', [
            'metrics' => $metrics,
            'availableRoles' => Role::where('name', '!=', 'admin')->get() // admin has access to all inherently
        ]);
    }

    public function update(Request $request, Metric $metric)
    {
        $request->validate([
            'is_active' => 'boolean',
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id'
        ]);

        if ($request->has('is_active')) {
            $metric->update(['is_active' => $request->is_active]);
        }

        if ($request->has('role_ids')) {
            $metric->roles()->sync($request->role_ids);
        }

        return redirect()->back()->with('success', 'Metric updated successfully.');
    }
}
