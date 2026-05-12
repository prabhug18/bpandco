<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Slip;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEmployeeReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:employee-reminders {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminders to employees who have not submitted daily data.';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService)
    {
        if (!AppSetting::get('whatsapp_employee_reminder_enabled', false)) {
            $this->info('Employee reminders are currently disabled in settings.');
            return 0;
        }

        // Get date from argument or use today
        $dateStr = $this->argument('date') ?? Carbon::today()->toDateString();
        $date = Carbon::parse($dateStr);
        $formattedDate = $date->format('d M Y');

        $this->info("Sending employee reminders for {$formattedDate}...");

        // Get all non-admin users with a mobile number
        $users = User::whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereHas('roles', function ($q) {
                $q->where('name', '!=', 'admin');
            })
            ->get();

        $sentCount = 0;

        foreach ($users as $user) {
            // Check if user has submitted any slips for the given date
            $hasSlips = Slip::where('user_id', $user->id)
                ->whereDate('date', $date->toDateString())
                ->exists();

            if (!$hasSlips) {
                // Send reminder
                $response = $whatsAppService->sendEmployeeReminder($user->id, $user->mobile, $user->name, $formattedDate);
                
                if ($response['success']) {
                    $this->info("Sent reminder to {$user->name} ({$user->mobile})");
                    $sentCount++;
                } else {
                    $this->error("Failed to send reminder to {$user->name} ({$user->mobile})");
                }
            }
        }

        $this->info("Completed. Sent {$sentCount} reminders.");
        return 0;
    }
}
