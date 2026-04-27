<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncentiveConfiguration extends Model
{
    protected $guarded = [];

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }
}
