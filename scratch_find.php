<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;

$role = Role::where('name', 'RETAIL ENAMEL')->first();
if (!$role) { echo "Role not found\n"; exit; }

$tiers = DailyScoringTier::where('role_id', $role->id)->get();
$targets = PeriodTarget::where('role_id', $role->id)->get();

echo "=== DAILY SCORING TIERS ===\n";
foreach ($tiers as $t) {
    echo "Metric ID: {$t->metric_id} | Tier: {$t->tier_label} | Min: {$t->min_value} | Points: {$t->daily_points}\n";
}

echo "\n=== PERIOD TARGETS ===\n";
foreach ($targets as $t) {
    echo "Metric ID: {$t->metric_id} | Period: {$t->period_type} | Tier: {$t->tier_label} | Min: {$t->min_value} | Points: {$t->points_awarded}\n";
}
