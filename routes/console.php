<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly: calculate performance scores from approved slips at 11:59 PM
Schedule::command('scores:calculate')->dailyAt('23:59');

// Monthly: calculate incentives on the 1st of every month at 00:05 AM
Schedule::command('incentives:calculate')->monthlyOn(1, '00:05');

// Annual: calculate salary increments every April 1st at 00:10 AM
Schedule::command('increments:calculate')->yearlyOn(4, 1, '00:10');
