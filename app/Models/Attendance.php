<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attendance extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if (!$this->image_path) return null;
        
        // If it's a full URL already (unlikely with current logic but safe)
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        // Return URL from R2 disk
        return Storage::disk('r2')->url($this->image_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
