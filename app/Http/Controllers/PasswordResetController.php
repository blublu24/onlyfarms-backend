<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\PhpMailerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    public function __construct(private PhpMailerService $phpMailerService)
    {
    }

    /**
     * Handle password reset code request.
     */
    public function requestReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'We could not find an account with that email address.',
            ], 404);
        }

        $resetCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        DB::table('email_verifications')->updateOrInsert(
            ['email' => $user->email],
            [
                'verification_code' => $resetCode,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $emailSent = false;
        $method = '';
        $lastError = null;

        try {
            $this->sendWithPhpMailer($user->email, $user->name ?: 'OnlyFarms User', $resetCode);
            $emailSent = true;
            $method = 'PHPMailer';
        } catch (\Exception $phpMailerException) {
            Log::warning('Password reset PHPMailer failed, trying Laravel Mail', [
                'email' => $user->email,
                'error' => $phpMailerException->getMessage(),
            ]);

            try {
                Mail::to($user->email)->send(new PasswordResetMail($resetCode, $user->name));
                $emailSent = true;
                $method = 'Laravel Mail';
            } catch (\Exception $mailException) {
                $lastError = $mailException->getMessage();
                Log::error('Password reset email failed via all providers', [
                    'email' => $user->email,
                    'phpmailer_error' => $phpMailerException->getMessage(),
                    'laravel_mail_error' => $mailException->getMessage(),
                ]);
            }
        }

        if (!$emailSent) {
            return response()->json([
                'message' => 'We could not send the password reset code. Please try again shortly.',
                'error' => $lastError,
            ], 503);
        }

        Log::info('Password reset code sent', [
            'email' => $user->email,
            'method' => $method,
        ]);

        $payload = [
            'message' => 'Password reset code sent successfully.',
            'method' => $method,
            'expires_at' => $expiresAt->toISOString(),
        ];

        if (app()->environment('local')) {
            $payload['verification_code'] = $resetCode;
        }

        return response()->json($payload);
    }

    /**
     * Complete the password reset with the provided code.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $verification = DB::table('email_verifications')
            ->where('email', $request->email)
            ->where('verification_code', $request->verification_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Invalid or expired reset code. Please request a new one.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'We could not find an account with that email address.',
            ], 404);
        }

        $user->update([
            'password' => $request->password,
        ]);

        DB::table('email_verifications')->where('email', $request->email)->delete();

        Log::info('Password reset successful', ['email' => $user->email]);

        return response()->json([
            'message' => 'Password reset successful. You can now log in with your new password.',
        ]);
    }

    private function sendWithPhpMailer(string $email, string $name, string $code): void
    {
        $html = $this->generateHtmlEmail($code, $name);
        $text = $this->generateTextEmail($code, $name);

        $this->phpMailerService->send(
            $email,
            $name,
            'OnlyFarms - Password Reset Code',
            $html,
            $text
        );
    }

    private function generateHtmlEmail(string $code, string $name): string
    {
        return view('emails.password-reset-code', [
            'resetCode' => $code,
            'name' => $name,
        ])->render();
    }

    private function generateTextEmail(string $code, string $name): string
    {
        return "Hello {$name},\n\n"
            . "We received a request to reset your OnlyFarms password. "
            . "Use the code below to reset your password. The code will expire in 10 minutes.\n\n"
            . "RESET CODE: {$code}\n\n"
            . "If you did not request a password reset, please ignore this message or contact support.\n\n"
            . "OnlyFarms Support";
    }
}

