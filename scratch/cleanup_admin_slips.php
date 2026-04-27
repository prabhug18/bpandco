<?php
use App\Models\User;
use App\Models\Slip;
use App\Models\Metric;

// Load Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$attendanceMetric = Metric::where('key', 'attendance')->first();
$admins = User::role('admin')->pluck('id');

if ($attendanceMetric && $admins->isNotEmpty()) {
    $count = Slip::where('metric_id', $attendanceMetric->id)
        ->whereIn('user_id', $admins)
        ->where('comment', 'LIKE', 'Holiday Credit%')
        ->delete();
    echo "Deleted $count holiday credit slips for admins.\n";
} else {
    echo "No admins found or no slips to delete.\n";
}
