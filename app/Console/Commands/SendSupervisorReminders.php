<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Slip;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendSupervisorReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:supervisor-reminders {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminders to supervisors with pending approvals.';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService)
    {
        if (!AppSetting::get('whatsapp_supervisor_reminder_enabled', false)) {
            $this->info('Supervisor reminders are currently disabled in settings.');
            return 0;
        }

        // Get date from argument or use yesterday
        $dateStr = $this->argument('date') ?? Carbon::yesterday()->toDateString();
        $date = Carbon::parse($dateStr);
        $formattedDate = $date->format('d M Y');

        $this->info("Sending supervisor reminders for pending approvals from {$formattedDate}...");

        // Get all users who can approve slips and have a mobile number
        $supervisors = User::whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->permission('approve slips') // Requires spatie/laravel-permission trait on User model
            ->get();

        // Calculate pending approvals for the given date
        // Note: For supervisors, they approve globally. We just need to know if there are *any* pending globally.
        $pendingSlipsCount = Slip::where('status', 'pending')
            ->whereDate('date', $date->toDateString())
            ->whereHas('metric', fn($q) => $q->where('key', '!=', 'attendance'))
            ->count();

        $pendingAttendanceCount = Attendance::where('approval_status', 'pending')
            ->whereDate('date', $date->toDateString())
            ->count();

        $totalPending = $pendingSlipsCount + $pendingAttendanceCount;

        if ($totalPending === 0) {
            $this->info("No pending approvals found for {$formattedDate}. Nothing to send.");
            return 0;
        }

        $sentCount = 0;

        foreach ($supervisors as $supervisor) {
            $response = $whatsAppService->sendSupervisorReminder($supervisor->id, $supervisor->mobile, $supervisor->name, $totalPending, $formattedDate);
            
            if ($response['success']) {
                $this->info("Sent reminder to {$supervisor->name} ({$supervisor->mobile})");
                $sentCount++;
            } else {
                $this->error("Failed to send reminder to {$supervisor->name} ({$supervisor->mobile})");
            }
        }

        $this->info("Completed. Sent {$sentCount} reminders.");
        return 0;
    }
}
