<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\OtpVerificationMail;

class EmailOtpService
{
    /**
     * Send OTP verification code via email
     */
    public function sendOtp($email, $otpCode, $userName = null)
    {
        try {
            $mailData = [
                'otp' => $otpCode,
                'userName' => $userName,
                'email' => $email,
            ];

            Mail::to($email)->send(new OtpVerificationMail($mailData));

            Log::info("Email OTP sent successfully", [
                'email' => $email,
                'otp' => $otpCode
            ]);

            return [
                'success' => true,
                'message' => 'OTP sent to your email successfully'
            ];

        } catch (\Exception $e) {
            Log::error("Email OTP sending failed", [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a 6-digit OTP code
     */
    public function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Test email functionality
     */
    public function testEmail($email, $userName = 'Test User')
    {
        $otp = $this->generateOtp();
        return $this->sendOtp($email, $otp, $userName);
    }
}
