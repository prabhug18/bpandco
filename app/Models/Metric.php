<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
    // protected $guarded = []; is standard, let's use it
    protected $guarded = [];

    public function roles()
    {
        return $this->belongsToMany(\Spatie\Permission\Models\Role::class, 'metric_role');
    }

    public function dailyScoringTiers()
    {
        return $this->hasMany(DailyScoringTier::class)->orderBy('min_value', 'desc');
    }

    public function periodTargets()
    {
        return $this->hasMany(PeriodTarget::class)->orderBy('min_value', 'desc');
    }

    public function dailyScoringTiersForRole($roleId)
    {
        return $this->hasMany(DailyScoringTier::class)->where('role_id', $roleId)->orderBy('min_value', 'desc');
    }

    public function periodTargetsForRole($roleId)
    {
        return $this->hasMany(PeriodTarget::class)->where('role_id', $roleId)->orderBy('min_value', 'desc');
    }
}
