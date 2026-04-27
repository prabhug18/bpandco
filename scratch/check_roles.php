<?php
use Spatie\Permission\Models\Role;

// Load Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach(Role::all() as $r) {
    echo $r->name . ": " . $r->users()->count() . "\n";
}
