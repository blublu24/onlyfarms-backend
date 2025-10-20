<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Mail\VerificationCodeMail;

class EmailVerificationController extends Controller
{
    /**
     * Send verification code to email
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

        try {
            // Send verification email
            Mail::to($email)->send(new VerificationCodeMail($verificationCode));
            
            return response()->json([
                'message' => 'Verification code sent successfully!',
                'verification_code' => $verificationCode, // For development/testing
                'gmail_url' => $this->generateGmailUrl($email, $verificationCode),
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send verification code. Please try again.',
                'verification_code' => $verificationCode, // Still return for development
                'gmail_url' => $this->generateGmailUrl($email, $verificationCode),
            ], 200); // Return 200 with fallback code
        }
    }

    /**
     * Verify email with code and complete registration
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

        // Get the user record (should exist from sendVerificationCode)
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
            'password' => bcrypt($request->password),
            'email_verified_at' => now(),
        ]);

        // Clean up verification record
        DB::table('email_verifications')->where('email', $verification->email)->delete();

        // Create token for immediate login
        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully!',
            'user' => $user,
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

        try {
            // Send new verification email
            Mail::to($user->email)->send(new VerificationCodeMail($verificationCode));
            
            return response()->json([
                'message' => 'New verification code sent successfully!',
                'verification_code' => $verificationCode, // For development/testing
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to resend verification email: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to resend verification code. Please try again.',
                'verification_code' => $verificationCode, // Still return for development
            ], 200);
        }
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
