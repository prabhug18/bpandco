<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class OtpLoginController extends Controller
{
    /**
     * Display the OTP login view.
     */
    public function showOtpForm()
    {
        if (!AppSetting::get('whatsapp_otp_enabled', false)) {
            return redirect()->route('login')->withErrors(['error' => 'OTP login is currently disabled.']);
        }

        return Inertia::render('Auth/OtpLogin');
    }

    /**
     * Send OTP to the user's mobile.
     */
    public function sendOtp(Request $request, WhatsAppService $whatsAppService)
    {
        if (!AppSetting::get('whatsapp_otp_enabled', false)) {
            return back()->withErrors(['error' => 'OTP login is currently disabled.']);
        }

        $request->validate([
            'mobile' => 'required|string',
        ]);

        $mobile = $request->mobile;

        // Strip non-numeric characters for searching
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);
        
        // Find user by mobile. Since we might have different formats in DB, let's try to match the last 10 digits
        $mobileTail = substr($cleanMobile, -10);
        
        if (strlen($mobileTail) < 10) {
             return back()->withErrors(['mobile' => 'Invalid mobile number format.']);
        }

        $user = User::where('mobile', 'LIKE', '%' . $mobileTail . '%')->first();

        if (!$user) {
            return back()->withErrors(['mobile' => 'No account found with this mobile number.']);
        }

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store OTP
        OtpCode::create([
            'mobile' => $user->mobile,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified' => false,
        ]);

        // Send OTP via WhatsApp
        $response = $whatsAppService->sendOtp($user->mobile, $otp);

        if (!$response['success']) {
            return back()->withErrors(['mobile' => 'Failed to send OTP to your WhatsApp. Please try again later.']);
        }

        return redirect()->route('otp.form')->with([
            'otp_sent' => true,
            'mobile' => $user->mobile,
            'success' => 'OTP sent to your WhatsApp number successfully.'
        ]);
    }

    /**
     * Verify OTP and login.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $otpRecord = OtpCode::where('mobile', $request->mobile)
            ->where('otp', $request->otp)
            ->where('verified', false)
            ->where('expires_at', '>=', Carbon::now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        // Mark as verified
        $otpRecord->update(['verified' => true]);

        // Login user
        $user = User::where('mobile', $request->mobile)->first();
        
        if (!$user) {
            return back()->withErrors(['otp' => 'User not found.']);
        }

        Auth::login($user, $request->boolean('remember', true));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
