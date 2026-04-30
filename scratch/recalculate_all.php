<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\ScoringService;
use Carbon\Carbon;

$month = Carbon::today()->format('Y-m');
echo "Recalculating all scores for {$month}...\n";

$users = User::whereDoesntHave('roles', fn($q) => $q->where('name', 'admin'))->get();

foreach ($users as $user) {
    echo "Processing {$user->name}...\n";
    ScoringService::updateMonthScores($user, $month);
}

echo "Recalculation complete.\n";
