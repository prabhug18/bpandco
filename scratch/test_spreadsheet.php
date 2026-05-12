<?php
require __DIR__.'/../vendor/autoload.php';

try {
    $s = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    echo "Class found!\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
