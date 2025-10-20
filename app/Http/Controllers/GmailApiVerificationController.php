<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\GmailApiService;

class GmailApiVerificationController extends Controller
{
    private $gmailApi;

    public function __construct(GmailApiService $gmailApi)
    {
        $this->gmailApi = $gmailApi;
    }

    /**
     * Get Gmail OAuth2 authorization URL
     */
    public function getGmailAuthUrl(Request $request)
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
        $authUrl = $this->gmailApi->getAuthUrl($email);

        return response()->json([
            'message' => 'Gmail authorization URL generated',
            'auth_url' => $authUrl,
            'email' => $email
        ]);
    }

    /**
     * Handle Gmail OAuth2 callback
     */
    public function handleGmailCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->gmailApi->exchangeCodeForToken($request->code, $request->email);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to authorize Gmail access',
                'error' => $result['error']
            ], 400);
        }

        return response()->json([
            'message' => 'Gmail access authorized successfully',
            'email' => $request->email
        ]);
    }

    /**
     * Send verification email via Gmail API
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

        // Send email via Gmail API
        $result = $this->gmailApi->sendVerificationEmail($email, $verificationCode);

        if ($result['success']) {
            return response()->json([
                'message' => 'Verification email sent via Gmail API!',
                'verification_code' => $verificationCode, // For development
                'message_id' => $result['message_id']
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to send email via Gmail API',
                'error' => $result['error'],
                'verification_code' => $verificationCode // Fallback for development
            ], 500);
        }
    }

    /**
     * Auto-verify email by reading Gmail
     */
    public function autoVerifyEmail(Request $request)
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
        $result = $this->gmailApi->autoVerifyEmail($email);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Auto-verification failed',
                'error' => $result['error']
            ], 400);
        }

        // Verify the code against our database
        $verification = DB::table('email_verifications')
            ->where('email', $email)
            ->where('verification_code', $result['verification_code'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Verification code not found or expired',
            ], 400);
        }

        return response()->json([
            'message' => 'Email auto-verified successfully!',
            'verification_code' => $result['verification_code'],
            'email_id' => $result['email_id'],
            'sent_at' => $result['sent_at']
        ]);
    }

    /**
     * Search for verification emails in Gmail
     */
    public function searchVerificationEmails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $verificationCode = $request->verification_code;

        $result = $this->gmailApi->searchVerificationEmails($email, $verificationCode);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to search Gmail',
                'error' => $result['error']
            ], 400);
        }

        return response()->json([
            'message' => 'Gmail search completed',
            'emails' => $result['emails']
        ]);
    }

    /**
     * Complete verification with Gmail API
     */
    public function completeVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Try auto-verification first
        $autoVerifyResult = $this->gmailApi->autoVerifyEmail($request->email);

        if (!$autoVerifyResult['success']) {
            return response()->json([
                'message' => 'Please manually enter the verification code from your Gmail',
                'requires_manual_code' => true
            ], 400);
        }

        // Verify against database
        $verification = DB::table('email_verifications')
            ->where('email', $request->email)
            ->where('verification_code', $autoVerifyResult['verification_code'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Verification code not found or expired',
                'requires_manual_code' => true
            ], 400);
        }

        // Create user account
        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'email_verified_at' => now(),
        ]);

        // Clean up verification record
        DB::table('email_verifications')->where('email', $request->email)->delete();

        // Create token
        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified and account created successfully!',
            'user' => $user,
            'token' => $token,
            'auto_verified' => true
        ]);
    }
}
