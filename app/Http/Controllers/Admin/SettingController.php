<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $roleId = $request->role_id;
        $settings = AppSetting::where('role_id', $roleId)->pluck('value', 'key');
        
        $roles = \Spatie\Permission\Models\Role::all();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => (object)$settings,
            'roles' => $roles,
            'selected_role_id' => $roleId
        ]);
    }

    public function update(Request $request)
    {
        $roleId = $request->role_id;

        foreach ($request->except(['role_id']) as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key, 'role_id' => $roleId],
                ['value' => $value]
            );
        }

        return back()->with('success', 'System settings updated successfully.');
    }
}
