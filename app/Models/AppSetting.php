<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $guarded = [];

    public static function get($key, $default = null, $roleId = null)
    {
        $setting = self::where('key', $key)
            ->where('role_id', $roleId)
            ->first();
            
        if (!$setting && $roleId !== null) {
            // Fallback to global setting if role-specific not found
            $setting = self::where('key', $key)
                ->whereNull('role_id')
                ->first();
        }

        return $setting ? $setting->value : $default;
    }
}
