<?php
use App\Models\Holiday;
use App\Models\User;
use App\Models\Slip;
use App\Models\Metric;
use App\Models\DailyScoringTier;

// Load Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$attendanceMetric = Metric::where('key', 'attendance')->first();
if (!$attendanceMetric) {
    echo "Attendance metric not found\n";
    exit(1);
}

$attendancePoints = DailyScoringTier::where('metric_id', $attendanceMetric->id)
    ->where('min_value', 1.0)
    ->value('daily_points') ?? 0.67;

$holidays = Holiday::where('is_active', true)->get();

foreach ($holidays as $h) {
    if ($h->user_id) {
        $users = User::where('id', $h->user_id)->get();
    } else {
        $users = User::all();
    }

    foreach ($users as $user) {
        Slip::updateOrCreate(
            ['user_id' => $user->id, 'metric_id' => $attendanceMetric->id, 'date' => $h->date],
            [
                'value' => 1.0, 
                'daily_points_earned' => $attendancePoints, 
                'status' => 'approved', 
                'comment' => 'Holiday Credit: ' . $h->reason
            ]
        );
    }
}
echo "Attendance slips synchronized for " . $holidays->count() . " holidays.\n";
