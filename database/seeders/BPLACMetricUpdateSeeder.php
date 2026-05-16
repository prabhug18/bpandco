<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metric;

class BPLACMetricUpdateSeeder extends Seeder
{
    public function run()
    {
        // Update both Collection and BP LAC Products to use 'amount'
        Metric::where('key', 'collection')->update(['unit' => 'amount']);
        Metric::where('key', 'bplac_products')->update(['unit' => 'amount']);

        $this->command->info('Metric units updated to "amount" successfully!');
    }
}
