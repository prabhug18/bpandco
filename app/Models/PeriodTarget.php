<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodTarget extends Model
{
    protected $guarded = [];

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }
}
