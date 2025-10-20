<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\SmartEmailVerificationService;

class SmartEmailVerificationController extends Controller
{
    private $smartEmailService;

    public function __construct(SmartEmailVerificationService $smartEmailService)
    {
        $this->smartEmailService = $smartEmailService;
    }

    /**
     * Send verification email with smart Gmail integration
     */
    public function sendVerificationEmail(Request $request)
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
        $existingUser = \App\Models\User::where('email', $email)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'Email has been used already. Please use a different email address.',
            ], 409);
        }

        // Generate 6-digit verification code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store verification code in database
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

        // Send email with smart Gmail integration
        $result = $this->smartEmailService->sendVerificationEmail($email, $verificationCode);

        if ($result['success']) {
            return response()->json([
                'message' => 'Verification email sent successfully!',
                'verification_code' => $verificationCode, // For development
                'gmail_url' => $result['gmail_url'],
                'gmail_app_url' => $result['gmail_app_url'] ?? null,
                'instructions' => $result['instructions'],
                'is_gmail' => $this->smartEmailService->isGmailAddress($email),
                'assistance' => $this->smartEmailService->getGmailAssistance($email)
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to send verification email, but you can use the code below',
                'verification_code' => $verificationCode,
                'gmail_url' => $result['gmail_url'],
                'error' => $result['error']
            ], 200); // Still return 200 with fallback
        }
    }

    /**
     * Get Gmail assistance for user
     */
    public function getGmailAssistance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $assistance = $this->smartEmailService->getGmailAssistance($request->email);

        return response()->json([
            'message' => 'Gmail assistance generated',
            'assistance' => $assistance
        ]);
    }

    /**
     * Enhanced verification with Gmail features
     */
    public function enhancedVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store verification code
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

        // Send email
        $emailResult = $this->smartEmailService->sendVerificationEmail($email, $verificationCode);
        
        // Get enhanced verification data
        $enhancedData = $this->smartEmailService->enhancedVerification($email, $verificationCode);

        return response()->json([
            'message' => 'Enhanced verification initiated',
            'data' => $enhancedData,
            'email_sent' => $emailResult['success']
        ]);
    }

    /**
     * Verify email with code
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'verification_code' => 'required|string|size:6',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the verification record
        $verification = DB::table('email_verifications')
            ->where('verification_code', $request->verification_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Invalid or expired verification code. Please request a new one.',
            ], 400);
        }

        // Get the user record
        $user = \App\Models\User::find($request->user_id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found. Please start the registration process again.',
            ], 404);
        }

        // Update user with verified email
        $user->update([
            'email' => $verification->email,
            'name' => $request->name,
            'password' => bcrypt($request->password),
            'email_verified_at' => now(),
        ]);

        // Clean up verification record
        DB::table('email_verifications')->where('email', $verification->email)->delete();

        // Create token
        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully!',
            'user' => $user,
            'token' => $token,
        ]);
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

        $user = \App\Models\User::find($request->user_id);
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

        // Send new verification email
        $result = $this->smartEmailService->sendVerificationEmail($user->email, $verificationCode);

        return response()->json([
            'message' => 'New verification code sent successfully!',
            'verification_code' => $verificationCode,
            'gmail_url' => $result['gmail_url'],
            'gmail_app_url' => $result['gmail_app_url'] ?? null,
            'instructions' => $result['instructions']
        ]);
    }
}
