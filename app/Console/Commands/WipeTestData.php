<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WipeTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:wipe-test-data {--force : Force the operation to run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipes all operational test data (slips, attendance, scores) while preserving configuration.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('WARNING: This will PERMANENTLY DELETE all slips, attendances, and computed scores. Are you sure you want to proceed?')) {
            $this->info('Operation cancelled.');
            return 1;
        }

        $this->info('Starting data wipe for transactional tables...');

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $tablesToWipe = [
            'slips',
            'attendances',
            'performance_scores',
            'summary_reports',
            'day_basis_reports',
            'employee_incentives',
            'employee_increments'
        ];

        foreach ($tablesToWipe as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                \Illuminate\Support\Facades\DB::table($table)->truncate();
                $this->line(" - Wiped: {$table}");
            }
        }

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $this->info("\nAll test operational data has been successfully wiped!");
        return 0;
    }
}
