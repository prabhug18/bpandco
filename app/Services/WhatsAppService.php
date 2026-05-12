<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Get the configured WeConnext API URL.
     */
    protected function getApiUrl(): string
    {
        return AppSetting::get('whatsapp_api_url', 'https://app.weconnext.in/api/v1/messages/send');
    }

    /**
     * Get the configured WeConnext API Token.
     */
    protected function getApiToken(): ?string
    {
        return AppSetting::get('whatsapp_api_token');
    }

    /**
     * Format the mobile number to ensure it has the country code (91 for India).
     */
    public function formatMobile(string $mobile): string
    {
        // Remove spaces, dashes, and plus signs
        $mobile = preg_replace('/[\s\-+]/', '', $mobile);

        // Remove leading 0 if present
        if (str_starts_with($mobile, '0')) {
            $mobile = ltrim($mobile, '0');
        }

        // If it's a 10-digit number, prepend 91
        if (strlen($mobile) === 10) {
            $mobile = '91' . $mobile;
        }

        return $mobile;
    }

    /**
     * Send a WhatsApp message using WeConnext API.
     */
    protected function sendTemplateMessage(int $userId = null, string $mobile, string $templateName, array $variables = []): array
    {
        $apiUrl = $this->getApiUrl();
        $apiToken = $this->getApiToken();

        if (empty($apiToken)) {
            Log::warning("WhatsApp API token is not configured.");
            return ['success' => false, 'error' => 'API Token not configured.'];
        }

        $formattedMobile = $this->formatMobile($mobile);

        $payload = [
            'to' => $formattedMobile,
            'template_id' => $templateName,
            'variables' => array_values($variables)
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->post($apiUrl, $payload);

            $status = $response->successful() ? 'sent' : 'failed';
            $responseData = $response->json() ?? $response->body();

            // Log the request and response
            WhatsAppLog::create([
                'user_id' => $userId,
                'mobile' => $formattedMobile,
                'template' => $templateName,
                'params' => json_encode($variables),
                'status' => $status,
                'response' => json_encode($responseData),
            ]);

            return [
                'success' => $response->successful(),
                'response' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error("WhatsApp API Error: " . $e->getMessage());

            WhatsAppLog::create([
                'user_id' => $userId,
                'mobile' => $formattedMobile,
                'template' => $templateName,
                'params' => json_encode($variables),
                'status' => 'failed',
                'response' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send OTP to a user.
     */
    public function sendOtp(string $mobile, string $otp): array
    {
        $templateName = AppSetting::get('whatsapp_otp_template', 'bpandco_login_otp');

        $variables = [
            "1" => $otp
        ];

        return $this->sendTemplateMessage(null, $mobile, $templateName, $variables);
    }

    /**
     * Send reminder to an employee for missing daily entries.
     */
    public function sendEmployeeReminder(int $userId, string $mobile, string $name, string $date): array
    {
        $templateName = AppSetting::get('whatsapp_reminder_template', 'bpandco_employee_reminder');

        $variables = [
            "1" => $name,
            "2" => $date
        ];

        return $this->sendTemplateMessage($userId, $mobile, $templateName, $variables);
    }

    /**
     * Send reminder to a supervisor for pending approvals.
     */
    public function sendSupervisorReminder(int $userId, string $mobile, string $name, int $count, string $date): array
    {
        $templateName = AppSetting::get('whatsapp_approval_template', 'bpandco_supervisor_reminder');

        $variables = [
            "1" => $name,
            "2" => (string) $count,
            "3" => $date
        ];

        return $this->sendTemplateMessage($userId, $mobile, $templateName, $variables);
    }
}
