<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Holiday;
use App\Models\Slip;
use App\Models\Metric;
use App\Models\User;
use App\Services\ScoringService;

$lateMetric = Metric::where('key', 'late')->first();
if (!$lateMetric) {
    echo "Late metric not found.\n";
    exit;
}

$holidays = Holiday::where('is_active', true)->get();
echo "Found " . $holidays->count() . " active holidays.\n";

foreach ($holidays as $holiday) {
    echo "Processing Holiday: {$holiday->date} ({$holiday->reason})\n";
    
    if ($holiday->user_id) {
        $users = User::where('id', $holiday->user_id)->get();
    } else {
        $users = User::whereDoesntHave('roles', fn($q) => $q->where('name', 'admin'))->get();
    }

    foreach ($users as $user) {
        // Delete late slips on this holiday
        $deleted = Slip::where('user_id', $user->id)
            ->where('metric_id', $lateMetric->id)
            ->where('date', $holiday->date)
            ->delete();
            
        if ($deleted) {
            echo "  Removed Late slip for {$user->name} on {$holiday->date}\n";
        }
        
        // Recalculate month scores
        ScoringService::updateMonthScores($user, $holiday->date);
    }
}

echo "Cleanup complete.\n";
