<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use App\Services\PhpMailerService;

class OptimizedEmailVerificationController extends Controller
{
    private $phpMailerService;

    public function __construct(PhpMailerService $phpMailerService)
    {
        $this->phpMailerService = $phpMailerService;
    }

    /**
     * Send verification code to email using the most reliable method
     */
    public function sendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;

        // Check if email is already registered
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'Email has been used already. Please use a different email address.',
            ], 409);
        }

        // Generate 6-digit verification code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store verification code in database (expires in 10 minutes)
        $expiresAt = now()->addMinutes(10);
        
        DB::table('email_verifications')->updateOrInsert(
            ['email' => $email],
            [
                'verification_code' => $verificationCode,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Try PHPMailer first (most reliable), fallback to Laravel Mail
        $emailSent = false;
        $method = '';
        $lastError = null;

        try {
            // Method 1: PHPMailer (Primary - Most Reliable)
            $this->sendWithPhpMailer($email, $verificationCode);
            $emailSent = true;
            $method = 'PHPMailer';
            Log::info('Email sent successfully via PHPMailer', ['email' => $email]);
            
        } catch (\Exception $e) {
            Log::warning('PHPMailer failed, trying Laravel Mail', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            try {
                // Method 2: Laravel Mail (Fallback)
                Mail::to($email)->send(new VerificationCodeMail($verificationCode));
                $emailSent = true;
                $method = 'Laravel Mail';
                Log::info('Email sent successfully via Laravel Mail', ['email' => $email]);
                
            } catch (\Exception $e2) {
                Log::error('Both email methods failed', [
                    'email' => $email,
                    'phpmailer_error' => $e->getMessage(),
                    'laravel_mail_error' => $e2->getMessage()
                ]);
                $lastError = $e2->getMessage();
            }
        }

        if ($emailSent) {
            $payload = [
                'message' => 'Verification code sent successfully!',
                'method' => $method,
                'expires_at' => $expiresAt->toISOString(),
                'gmail_url' => $this->generateGmailUrl($email, $verificationCode),
            ];

            if (app()->environment('local')) {
                $payload['verification_code'] = $verificationCode;
            }

            return response()->json($payload, 200);
        }

        Log::error('Failed to send verification code via all providers', [
            'email' => $email,
            'last_error' => $lastError,
        ]);

        return response()->json([
            'message' => 'We could not send the verification code. Please try again shortly.'
        ], 503);
    }

    /**
     * Send email using PHPMailer
     */
    private function sendWithPhpMailer($email, $verificationCode)
    {
        $htmlBody = $this->generateHtmlEmail($verificationCode);
        $textBody = $this->generateTextEmail($verificationCode);
        
        $this->phpMailerService->send(
            $email,
            'OnlyFarms User',
            'OnlyFarms - Email Verification Code',
            $htmlBody,
            $textBody
        );
    }

    /**
     * Generate HTML email content
     */
    private function generateHtmlEmail($verificationCode)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Email Verification - OnlyFarms</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2e7d32; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .code { background: #fff; border: 2px solid #2e7d32; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; color: #2e7d32; margin: 20px 0; border-radius: 8px; letter-spacing: 4px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🌱 OnlyFarms</h1>
                    <h2>Email Verification</h2>
                </div>
                <div class='content'>
                    <p>Hello!</p>
                    <p>Thank you for signing up with OnlyFarms. To complete your registration, please use the verification code below:</p>
                    
                    <div class='code'>{$verificationCode}</div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong> This code will expire in 10 minutes for security reasons.
                    </div>
                    
                    <p>If you didn't request this verification code, please ignore this email.</p>
                    <p>Welcome to OnlyFarms - Your trusted farming marketplace!</p>
                </div>
                <div class='footer'>
                    <p>This email was sent by OnlyFarms</p>
                    <p>© 2025 OnlyFarms. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Generate text email content
     */
    private function generateTextEmail($verificationCode)
    {
        return "
ONLYFARMS - EMAIL VERIFICATION

Hello!

Thank you for signing up with OnlyFarms. To complete your registration, please use the verification code below:

VERIFICATION CODE: {$verificationCode}

⚠️ IMPORTANT: This code will expire in 10 minutes for security reasons.

If you didn't request this verification code, please ignore this email.

Welcome to OnlyFarms - Your trusted farming marketplace!

---
This email was sent by OnlyFarms
© 2025 OnlyFarms. All rights reserved.
        ";
    }

    /**
     * Verify email with code and complete registration
     */
    public function verifyEmail(Request $request)
    {
        // Support both flows: with user_id (existing user) or without (new signup)
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'verification_code' => 'required|string|size:6',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
            'phone_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the verification record by email and code
        $verification = DB::table('email_verifications')
            ->where('email', $request->email)
            ->where('verification_code', $request->verification_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Invalid or expired verification code. Please request a new one.',
            ], 400);
        }

        // Check if user already exists with this email
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'Email has been used already. Please use a different email address.',
            ], 409);
        }

        // Create new user if user_id not provided (new signup flow)
        if (!$request->user_id) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone_number' => $request->phone_number ?? null,
                'email_verified_at' => now(),
            ]);
        } else {
            // Get existing user (legacy flow)
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'message' => 'User not found. Please start the registration process again.',
                ], 404);
            }

            // Update user with verified email and complete registration
            $user->update([
                'email' => $verification->email,
                'name' => $request->name,
                'password' => $request->password,
                'email_verified_at' => now(),
            ]);
        }

        // Clean up verification record
        DB::table('email_verifications')->where('email', $verification->email)->delete();

        // Create token for immediate login
        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        Log::info('Email verification successful', [
            'user_id' => $user->id,
            'email' => $verification->email
        ]);

        return response()->json([
            'message' => 'Email verified successfully! Welcome to OnlyFarms!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
            'token' => $token,
        ], 200);
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Generate new verification code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        // Update verification record
        DB::table('email_verifications')->updateOrInsert(
            ['email' => $user->email],
            [
                'verification_code' => $verificationCode,
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]
        );

        // Try PHPMailer first, fallback to Laravel Mail
        $emailSent = false;
        $method = '';
        $lastError = null;

        try {
            $this->sendWithPhpMailer($user->email, $verificationCode);
            $emailSent = true;
            $method = 'PHPMailer';
        } catch (\Exception $e) {
            try {
                Mail::to($user->email)->send(new VerificationCodeMail($verificationCode));
                $emailSent = true;
                $method = 'Laravel Mail';
            } catch (\Exception $e2) {
                Log::error('Failed to resend verification email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'phpmailer_error' => $e->getMessage(),
                    'laravel_mail_error' => $e2->getMessage()
                ]);
                $lastError = $e2->getMessage();
            }
        }

        if ($emailSent) {
            $payload = [
                'message' => 'New verification code sent successfully!',
                'method' => $method,
                'expires_at' => $expiresAt->toISOString(),
                'gmail_url' => $this->generateGmailUrl($user->email, $verificationCode),
            ];

            if (app()->environment('local')) {
                $payload['verification_code'] = $verificationCode;
            }

            return response()->json($payload, 200);
        }

        Log::error('Failed to deliver verification code on resend', [
            'user_id' => $user->id,
            'email' => $user->email,
            'last_error' => $lastError,
        ]);

        return response()->json([
            'message' => 'Failed to resend verification code. Please try again.'
        ], 503);
    }

    /**
     * Generate Gmail URL for easy access
     */
    private function generateGmailUrl($email, $code)
    {
        $subject = 'OnlyFarms Verification Code';
        $body = "Your verification code is: {$code}\n\nThis code will expire in 10 minutes.";
        
        return "https://mail.google.com/mail/?view=cm&to=" . urlencode($email) . 
               "&su=" . urlencode($subject) . 
               "&body=" . urlencode($body);
    }
}

