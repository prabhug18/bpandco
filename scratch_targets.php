<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\Metric;
use App\Models\PeriodTarget;

$role = Role::where('name', 'RETAIL ENAMEL')->first();
$metric = Metric::where('key', 'late')->first();

if($role && $metric) {
    $monthlyTargets = [
        ['period_type' => 'monthly', 'tier_label' => 'green', 'min_value' => 4, 'points_awarded' => 15],
        ['period_type' => 'monthly', 'tier_label' => 'yellow', 'min_value' => 5, 'points_awarded' => 10],
        ['period_type' => 'monthly', 'tier_label' => 'red', 'min_value' => 6, 'points_awarded' => 7],
        ['period_type' => 'monthly', 'tier_label' => 'grey', 'min_value' => 7, 'points_awarded' => 4],
    ];

    foreach($monthlyTargets as $target) {
        PeriodTarget::updateOrCreate(
            ['role_id' => $role->id, 'metric_id' => $metric->id, 'period_type' => 'monthly', 'tier_label' => $target['tier_label']],
            ['min_value' => $target['min_value'], 'points_awarded' => $target['points_awarded'], 'updated_by' => 1]
        );
    }
    echo "Targets added for Retail Enamel\n";
} else {
    echo "Role or Metric not found\n";
}
