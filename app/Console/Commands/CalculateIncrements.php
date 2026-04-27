<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SummaryReport;
use App\Models\IncrementConfiguration;
use App\Models\EmployeeIncrement;
use Carbon\Carbon;

class CalculateIncrements extends Command
{
    protected $signature = 'increments:calculate {--year= : Financial year start (e.g. 2026 = Apr 2026 – Mar 2027)}';
    protected $description = 'Annual: count green months across a financial year and apply increment slab to each employee.';

    public function handle()
    {
        // Financial year: April to March
        $yearStart = $this->option('year') ?? Carbon::today()->year;
        $start = Carbon::create($yearStart, 4, 1);
        $end   = Carbon::create($yearStart + 1, 3, 31);

        $this->info("Calculating increments for FY {$yearStart}-" . ($yearStart + 1));

        $users = User::with('roles')->get();

        foreach ($users as $user) {
            $greenMonths = 0;

            // Iterate Apr → Mar (12 months)
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $report = SummaryReport::where('user_id', $user->id)
                    ->where('year_month', $cursor->format('Y-m'))
                    ->first();

                if ($report && $report->overall_traffic_light === 'green') {
                    $greenMonths++;
                }
                $cursor->addMonth();
            }

            // Find matching increment slab
            $roleId = $user->roles->first()?->id;

            $slab = IncrementConfiguration::where('green_months_required', '<=', $greenMonths)
                ->where(function ($q) use ($roleId) {
                    $q->where('role_id', $roleId)->orWhereNull('role_id');
                })
                ->orderBy('increment_percentage', 'desc')
                ->first();

            $incrementPct = $slab ? $slab->increment_percentage : 0;

            EmployeeIncrement::updateOrCreate(
                ['user_id' => $user->id, 'year' => (string) $yearStart],
                [
                    'green_months_count'   => $greenMonths,
                    'increment_percentage' => $incrementPct,
                ]
            );

            $this->line("  {$user->name}: {$greenMonths}/12 green months → {$incrementPct}% increment");
        }

        $this->info('Increment calculation complete.');
    }
}
