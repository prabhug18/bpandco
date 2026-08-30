<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ExternalApiToken;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $roleId = $request->role_id;
        $settings = AppSetting::where('role_id', $roleId)->pluck('value', 'key');
        
        $roles = \Spatie\Permission\Models\Role::all();
        $apiTokens = ExternalApiToken::with('creator')->latest()->get();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => (object)$settings,
            'roles' => $roles,
            'selected_role_id' => $roleId,
            'api_tokens' => $apiTokens,
            'new_token' => session('new_plain_token'),
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

    public function storeApiToken(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'store_code' => 'nullable|string|max:50',
        ]);

        $created = ExternalApiToken::createToken($request->name, $request->store_code, auth()->id());

        return back()->with('success', 'API Token created successfully. Make sure to copy it now!')
            ->with('new_plain_token', $created['plain_text_token']);
    }

    public function revokeApiToken(ExternalApiToken $token)
    {
        $token->update(['is_active' => false]);
        return back()->with('success', "API Token '{$token->name}' has been revoked.");
    }
}

