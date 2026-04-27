<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::all()->pluck('value', 'key');
        
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'attendance_work_start' => 'required',
            'attendance_grace_end'  => 'required',
            'attendance_half_day'   => 'required',
        ]);

        foreach ($request->all() as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'System settings updated successfully.');
    }
}
