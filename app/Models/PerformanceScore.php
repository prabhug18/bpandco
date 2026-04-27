<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceScore extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function metric()
    {
        return $this->belongsTo(Metric::class);
    }
}
