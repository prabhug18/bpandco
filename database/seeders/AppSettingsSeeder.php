<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AppSetting;

class AppSettingsSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            'attendance_work_start' => '09:00',
            'attendance_grace_end' => '09:15',
            'attendance_half_day' => '10:00',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
