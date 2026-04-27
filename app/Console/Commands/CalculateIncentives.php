<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SummaryReport;
use App\Models\IncentiveConfiguration;
use App\Models\EmployeeIncentive;
use Carbon\Carbon;

class CalculateIncentives extends Command
{
    protected $signature = 'incentives:calculate {--month= : Year-month to calculate (default: last month)}';
    protected $description = 'Monthly: compare each employee total score against incentive slabs and record payable incentives.';

    public function handle()
    {
        $targetMonth = $this->option('month')
            ? Carbon::parse($this->option('month') . '-01')
            : Carbon::today()->subMonth();

        $yearMonth = $targetMonth->format('Y-m');
        $this->info("Calculating incentives for: {$yearMonth}");

        $users = User::with('roles')->get();

        foreach ($users as $user) {
            $summary = SummaryReport::where('user_id', $user->id)
                ->where('year_month', $yearMonth)
                ->first();

            if (!$summary) {
                $this->line("  No summary for user {$user->name}, skipping.");
                continue;
            }

            // Count consecutive green months up to this month
            $consecutiveGreen = $this->countConsecutiveGreen($user->id, $yearMonth);

            // Find matching incentive slab (role-specific first, then global)
            $roleId = $user->roles->first()?->id;

            $slab = IncentiveConfiguration::where('is_active', true)
                ->where('consecutive_green_months', '<=', $consecutiveGreen)
                ->where('min_score', '<=', $summary->total_mark)
                ->where(function ($q) use ($roleId) {
                    $q->where('role_id', $roleId)->orWhereNull('role_id');
                })
                ->orderBy('incentive_amount', 'desc') // highest matching slab wins
                ->first();

            $amount = $slab ? $slab->incentive_amount : 0;

            EmployeeIncentive::updateOrCreate(
                ['user_id' => $user->id, 'year_month' => $yearMonth],
                [
                    'total_score'              => $summary->total_mark,
                    'consecutive_green_months' => $consecutiveGreen,
                    'incentive_amount'         => $amount,
                    'status'                   => 'pending',
                ]
            );

            $this->line("  {$user->name}: score={$summary->total_mark}, green={$consecutiveGreen}m → ₹{$amount}");
        }

        $this->info('Incentive calculation complete.');
    }

    private function countConsecutiveGreen(int $userId, string $upToMonth): int
    {
        $count = 0;
        $month = Carbon::parse($upToMonth . '-01');

        while (true) {
            $report = SummaryReport::where('user_id', $userId)
                ->where('year_month', $month->format('Y-m'))
                ->first();

            if (!$report || $report->overall_traffic_light !== 'green') break;

            $count++;
            $month->subMonth();
        }

        return $count;
    }
}
