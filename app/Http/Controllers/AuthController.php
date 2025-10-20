<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Services\SmsService;
use App\Services\EmailOtpService;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // REGISTER method
    public function register(Request $request)
    {
        $signupMethod = $request->input('signup_method', 'email');
        
        if ($signupMethod === 'phone') {
            return $this->registerWithPhone($request);
        }
        
        // Original email registration
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed', // ✅ requires password_confirmation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create user with email (unverified)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_seller' => false,
            'email_verified_at' => null, // Will be set after OTP verification
        ]);

        // Generate and send OTP via email
        $emailOtpService = new EmailOtpService();
        $otp = $emailOtpService->generateOtp();
        
        // Store OTP in database
        $user->update([
            'email_verification_code' => $otp,
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via email
        $emailResult = $emailOtpService->sendOtp($request->email, $otp, $request->name);
        
        if (!$emailResult['success']) {
            \Log::error("Failed to send email OTP", [
                'email' => $request->email,
                'error' => $emailResult['error']
            ]);
        }
        
        // Log OTP for development/testing
        if (app()->environment('local')) {
            \Log::info("Email OTP for {$request->email}: {$otp}");
        }

        return response()->json([
            'message' => 'User created. Please verify your email address.',
            'user_id' => $user->id,
            'redirect_to_verification' => true,
            'verification_code' => app()->environment('local') ? $otp : null, // Only in development
        ], 201);
    }

    // REGISTER WITH PHONE method
    public function registerWithPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users|min:11|max:13',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Custom phone number validation for Philippine format
        $phoneNumber = $request->phone_number;
        if (!$this->isValidPhilippinePhoneNumber($phoneNumber)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['phone_number' => ['Please enter a valid Philippine mobile number (09XX-XXX-XXXX)']]
            ], 422);
        }

        // Create user with phone number (unverified)
        $user = User::create([
            'name' => $request->name,
            'email' => null, // No email for phone registration
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'is_seller' => false,
            'phone_verified_at' => null, // Will be set after OTP verification
        ]);

        // Generate and send OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in database (you might want to create a separate table for this)
        $user->update([
            'phone_verification_code' => $otp,
            'phone_verification_expires_at' => now()->addMinutes(5),
        ]);

        // Send SMS with OTP using Twilio
        $smsService = new SmsService();
        $smsResult = $smsService->sendOtp($request->phone_number, $otp);
        
        if (!$smsResult['success']) {
            \Log::error("Failed to send SMS OTP", [
                'phone_number' => $request->phone_number,
                'error' => $smsResult['error']
            ]);
            
            // Still return success but log the SMS failure
            // In production, you might want to return an error here
        }
        
        // Log OTP for development/testing
        if (app()->environment('local')) {
            \Log::info("Phone OTP for {$request->phone_number}: {$otp}");
        }

        return response()->json([
            'message' => 'User created. Please verify your phone number.',
            'user_id' => $user->id,
            'redirect_to_verification' => true,
            'verification_code' => app()->environment('local') ? $otp : null, // Only in development
        ], 201);
    }

    // LOGIN method
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
            'password' => 'required|string',
        ]);

        // Determine login method (email or phone)
        $loginField = $request->email ? 'email' : 'phone_number';
        $loginValue = $request->email ? $request->email : $request->phone_number;

        if (!$loginValue) {
            return response()->json([
                'message' => 'Either email or phone number is required',
                'error' => 'Authentication failed'
            ], 401);
        }

        $user = User::where($loginField, $loginValue)->first();

        // Check if user exists and has a valid password
        if (!$user) {
            return response()->json([
                'message' => 'User not found. If you signed up with Facebook, please use Facebook login instead.',
                'error' => 'Authentication failed'
            ], 401);
        }

        // Check if this is a Facebook user (no password set)
        if ($user->facebook_id && !$user->password) {
            return response()->json([
                'message' => 'This account was created with Facebook. Please use Facebook login instead.',
                'error' => 'Facebook account detected'
            ], 401);
        }

        // Check password for regular users
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'error' => 'Authentication failed'
            ], 401);
        }

        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful!',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // LOGOUT method
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    // UPDATE USER PROFILE
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
        ];

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time() . '_' . $user->id . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/profiles'), $imageName);
            $updateData['profile_image'] = 'uploads/profiles/' . $imageName;
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user' => $user->fresh()
        ]);
    }

    // SEND PHONE VERIFICATION CODE
    public function sendPhoneVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|min:11|max:13',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Custom phone number validation for Philippine format
        $phoneNumber = $request->phone_number;
        if (!$this->isValidPhilippinePhoneNumber($phoneNumber)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['phone_number' => ['Please enter a valid Philippine mobile number (09XX-XXX-XXXX)']]
            ], 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not found with this phone number'
            ], 404);
        }

        // Generate new OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $user->update([
            'phone_verification_code' => $otp,
            'phone_verification_expires_at' => now()->addMinutes(5),
        ]);

        // TODO: Send SMS with OTP using SMS service (Twilio, etc.)
        if (app()->environment('local')) {
            \Log::info("Phone OTP for {$request->phone_number}: {$otp}");
        }

        return response()->json([
            'message' => 'Verification code sent successfully',
            'user_id' => $user->id,
            'verification_code' => app()->environment('local') ? $otp : null, // Only in development
        ]);
    }

    // RESEND PHONE VERIFICATION CODE
    public function resendPhoneVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);
        
        if (!$user || !$user->phone_number) {
            return response()->json([
                'message' => 'User not found or no phone number'
            ], 404);
        }

        // Generate new OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $user->update([
            'phone_verification_code' => $otp,
            'phone_verification_expires_at' => now()->addMinutes(5),
        ]);

        // Send SMS with OTP using Twilio
        $smsService = new SmsService();
        $smsResult = $smsService->sendOtp($user->phone_number, $otp);
        
        if (!$smsResult['success']) {
            \Log::error("Failed to resend SMS OTP", [
                'phone_number' => $user->phone_number,
                'error' => $smsResult['error']
            ]);
        }
        
        // Log OTP for development/testing
        if (app()->environment('local')) {
            \Log::info("Resent Phone OTP for {$user->phone_number}: {$otp}");
        }

        return response()->json([
            'message' => 'Verification code resent successfully',
            'verification_code' => app()->environment('local') ? $otp : null, // Only in development
        ]);
    }

    // VERIFY PHONE
    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
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

        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Check if OTP is valid and not expired
        if ($user->phone_verification_code !== $request->verification_code) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], 400);
        }

        if ($user->phone_verification_expires_at && $user->phone_verification_expires_at->isPast()) {
            return response()->json([
                'message' => 'Verification code has expired'
            ], 400);
        }

        // Update user with verified phone and final details
        $user->update([
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_expires_at' => null,
        ]);

        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        return response()->json([
            'message' => 'Phone verified successfully!',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // EMAIL VERIFICATION METHODS

    /**
     * Send email verification code
     */
    public function sendEmailVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|min:11|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Custom email validation for Gmail
        if (!str_ends_with($request->email, '@gmail.com')) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['email' => ['Please use a Gmail address']]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found with this email address'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified'
            ], 400);
        }

        // Generate and send OTP
        $emailOtpService = new EmailOtpService();
        $otp = $emailOtpService->generateOtp();
        
        $user->update([
            'email_verification_code' => $otp,
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via email
        $emailResult = $emailOtpService->sendOtp($request->email, $otp, $user->name);
        
        if (!$emailResult['success']) {
            \Log::error("Failed to send email OTP", [
                'email' => $request->email,
                'error' => $emailResult['error']
            ]);
        }
        
        // Log OTP for development/testing
        if (app()->environment('local')) {
            \Log::info("Email OTP for {$request->email}: {$otp}");
        }

        return response()->json([
            'message' => 'Verification code sent to your email',
            'verification_code' => app()->environment('local') ? $otp : null, // Only in development
        ]);
    }

    /**
     * Resend email verification code
     */
    public function resendEmailVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
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
                'message' => 'User not found'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified'
            ], 400);
        }

        // Generate and send new OTP
        $emailOtpService = new EmailOtpService();
        $otp = $emailOtpService->generateOtp();
        
        $user->update([
            'email_verification_code' => $otp,
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via email
        $emailResult = $emailOtpService->sendOtp($user->email, $otp, $user->name);
        
        if (!$emailResult['success']) {
            \Log::error("Failed to resend email OTP", [
                'email' => $user->email,
                'error' => $emailResult['error']
            ]);
        }
        
        // Log OTP for development/testing
        if (app()->environment('local')) {
            \Log::info("Resent Email OTP for {$user->email}: {$otp}");
        }

        return response()->json([
            'message' => 'Verification code resent successfully',
            'verification_code' => app()->environment('local') ? $otp : null, // Only in development
        ]);
    }

    /**
     * Verify email with OTP
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
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

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Check if verification code is valid and not expired
        if (!$user->email_verification_code || 
            $user->email_verification_code !== $request->verification_code ||
            !$user->email_verification_expires_at ||
            $user->email_verification_expires_at->isPast()) {
            return response()->json([
                'message' => 'Invalid or expired verification code'
            ], 400);
        }

        // Update user with verified email and complete profile
        $user->update([
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ]);

        // Create token for the user
        $token = $user->createToken('onlyfarms_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully',
            'user' => $user,
            'token' => $token
        ]);
    }

    // Helper method to validate Philippine phone numbers
    private function isValidPhilippinePhoneNumber($phoneNumber)
    {
        // Remove all non-digit characters
        $cleanPhone = preg_replace('/\D/', '', $phoneNumber);
        
        // Check if it's a valid Philippine mobile number
        // Accepts: 09XX-XXX-XXXX, +639XX-XXX-XXXX, or 639XX-XXX-XXXX
        return preg_match('/^(09|639)\d{9}$/', $cleanPhone);
    }

    /**
     * Redirect to Facebook for authentication
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    /**
     * Handle Facebook callback (for mobile app)
     */
    public function handleFacebookCallback(Request $request)
    {
        try {
            $code = $request->get('code');
            $state = $request->get('state');
            
            if (!$code) {
                return response()->json([
                    'message' => 'Authorization code not provided'
                ], 400);
            }
            
            // Exchange code for access token
            $clientId = config('services.facebook.client_id');
            $clientSecret = config('services.facebook.client_secret');
            $redirectUri = config('services.facebook.redirect');
            
            $tokenResponse = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);
            
            if (!$tokenResponse->successful()) {
                return response()->json([
                    'message' => 'Failed to exchange code for token'
                ], 400);
            }
            
            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];
            
            // Get user info from Facebook (without email for now)
            $userResponse = Http::get('https://graph.facebook.com/v18.0/me', [
                'fields' => 'id,name,picture.width(500).height(500)', // Higher quality profile picture
                'access_token' => $accessToken,
            ]);
            
            // If the first request fails, try with a simpler picture request
            if (!$userResponse->successful()) {
                $userResponse = Http::get('https://graph.facebook.com/v18.0/me', [
                    'fields' => 'id,name,picture',
                    'access_token' => $accessToken,
                ]);
            }
            
            if (!$userResponse->successful()) {
                return response()->json([
                    'message' => 'Failed to get user info from Facebook'
                ], 400);
            }
            
            $facebookUser = $userResponse->json();
            
            // Debug Facebook user data
            \Log::info('Facebook User Data:', [
                'user_data' => $facebookUser,
                'has_picture' => isset($facebookUser['picture']),
                'picture_data' => $facebookUser['picture'] ?? null
            ]);
            
            // Check if user already exists by Facebook ID first (most reliable)
            $user = User::where('facebook_id', $facebookUser['id'])->first();
            
            if ($user) {
                // User exists with this Facebook ID, login them in
                // Update profile picture if not set or if Facebook has a newer one
                $profileImageUrl = null;
                if (isset($facebookUser['picture']['data']['url'])) {
                    $profileImageUrl = $facebookUser['picture']['data']['url'];
                } else {
                    // Fallback: construct Facebook profile picture URL manually
                    $profileImageUrl = "https://graph.facebook.com/{$facebookUser['id']}/picture?type=large";
                }
                
                // Always update profile image with latest Facebook picture
                $user->update(['profile_image' => $profileImageUrl]);
                $user->refresh(); // Refresh to get updated data
                
                \Log::info('Updated existing user profile image:', [
                    'user_id' => $user->id,
                    'profile_image_url' => $profileImageUrl
                ]);
                
                $token = $user->createToken('auth_token')->plainTextToken;
                
                return response()->json([
                    'message' => 'Login successful',
                    'user' => $user,
                    'token' => $token,
                    'is_new_user' => false
                ], 200);
            }
            
            // User doesn't exist - they need to sign up first
            return response()->json([
                'message' => 'Facebook account not registered',
                'error' => 'ACCOUNT_NOT_FOUND',
                'facebook_user' => [
                    'id' => $facebookUser['id'],
                    'name' => $facebookUser['name'],
                    'profile_picture' => isset($facebookUser['picture']['data']['url']) 
                        ? $facebookUser['picture']['data']['url'] 
                        : "https://graph.facebook.com/{$facebookUser['id']}/picture?type=large"
                ],
                'requires_signup' => true
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Facebook authentication failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // Facebook Signup - Create new user with Facebook data
    public function facebookSignup(Request $request)
    {
        try {
            $request->validate([
                'facebook_id' => 'required|string',
                'name' => 'required|string',
                'profile_picture' => 'nullable|string'
            ]);

            // Check if Facebook ID already exists
            $existingUser = User::where('facebook_id', $request->facebook_id)->first();
            if ($existingUser) {
                return response()->json([
                    'message' => 'Facebook account already registered',
                    'error' => 'ACCOUNT_EXISTS'
                ], 409);
            }

            // Create new user with Facebook data
            $user = User::create([
                'name' => $request->name,
                'email' => null, // No email from Facebook
                'email_verified_at' => null,
                'facebook_id' => $request->facebook_id,
                'profile_image' => $request->profile_picture,
                'is_seller' => false,
                'password' => Hash::make(uniqid()), // Random password
            ]);

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            \Log::info('Created new user with Facebook signup:', [
                'user_id' => $user->id,
                'facebook_id' => $request->facebook_id,
                'name' => $request->name
            ]);

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
                'is_new_user' => true
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Facebook signup failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Facebook login URL for mobile app
     */
    public function getFacebookLoginUrl()
    {
        try {
            // Create a state parameter for security
            $state = bin2hex(random_bytes(16));
            
            // Build the Facebook OAuth URL manually to avoid session dependency
            $clientId = config('services.facebook.client_id');
            $redirectUri = config('services.facebook.redirect');
            
            $params = [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => 'public_profile', // Temporarily remove email scope until Facebook app is configured
                'response_type' => 'code',
                'state' => $state,
            ];
            
            $facebookLoginUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
            
            // Debug logging
            \Log::info('Facebook Login URL Generated:', [
                'redirect_uri' => $redirectUri,
                'facebook_login_url' => $facebookLoginUrl,
                'client_id' => $clientId
            ]);
            
            return response()->json([
                'facebook_login_url' => $facebookLoginUrl,
                'state' => $state,
                'debug' => [
                    'redirect_uri' => $redirectUri,
                    'app_url' => config('app.url'),
                    'client_id' => $clientId,
                    'facebook_app_domain' => 'abc123.ngrok.io'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Facebook login URL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user exists by Facebook ID (for login detection)
     */
    public function checkFacebookUser(Request $request)
    {
        try {
            $request->validate([
                'facebook_id' => 'required|string'
            ]);

            $user = User::where('facebook_id', $request->facebook_id)->first();

            if ($user) {
                return response()->json([
                    'exists' => true,
                    'user' => $user,
                    'message' => 'User found'
                ]);
            }

            return response()->json([
                'exists' => false,
                'message' => 'User not found'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Google OAuth login URL for mobile app
     */
    public function getGoogleLoginUrl()
    {
        try {
            // Create a state parameter for security
            $state = bin2hex(random_bytes(16));
            
            // Build the Google OAuth URL manually to avoid session dependency
            $clientId = config('services.google.client_id');
            $redirectUri = config('services.google.redirect');
            
            $params = [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => 'openid email profile',
                'response_type' => 'code',
                'state' => $state,
                'device_id' => 'onlyfarms-mobile-app',
                'device_name' => 'OnlyFarms Mobile App',
            ];
            
            $googleLoginUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
            
            return response()->json([
                'google_login_url' => $googleLoginUrl,
                'state' => $state
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Google login URL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback (for mobile app)
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $code = $request->get('code');
            $state = $request->get('state');
            
            if (!$code) {
                return response()->json([
                    'message' => 'Authorization code not provided'
                ], 400);
            }
            
            // Exchange code for access token
            $clientId = config('services.google.client_id');
            $clientSecret = config('services.google.client_secret');
            $redirectUri = config('services.google.redirect');
            
            $tokenResponse = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);
            
            if (!$tokenResponse->successful()) {
                return response()->json([
                    'message' => 'Failed to exchange code for token'
                ], 400);
            }
            
            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];
            
            // Get user info from Google
            $userResponse = Http::get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'access_token' => $accessToken,
            ]);
            
            if (!$userResponse->successful()) {
                return response()->json([
                    'message' => 'Failed to get user info from Google'
                ], 400);
            }
            
            $googleUser = $userResponse->json();
            
            // Check if user already exists by Google ID first
            $user = User::where('google_id', $googleUser['id'])->first();
            
            if ($user) {
                // User exists with this Google ID, login them in
                $token = $user->createToken('auth_token')->plainTextToken;
                
                return response()->json([
                    'message' => 'Login successful',
                    'user' => $user,
                    'token' => $token,
                    'is_new_user' => false
                ], 200);
            }
            
            // Check if user exists by email (in case they signed up with email first)
            $user = User::where('email', $googleUser['email'])->first();
            
            if ($user) {
                // User exists with this email, link their Google account
                $user->update([
                    'google_id' => $googleUser['id'],
                    'email_verified_at' => now(), // Google verified users
                ]);
                
                $token = $user->createToken('auth_token')->plainTextToken;
                
                return response()->json([
                    'message' => 'Google account linked successfully',
                    'user' => $user,
                    'token' => $token,
                    'is_new_user' => false
                ], 200);
            }
            
            // User doesn't exist - they need to sign up first
            return response()->json([
                'message' => 'Google account not registered',
                'error' => 'ACCOUNT_NOT_FOUND',
                'google_user' => [
                    'id' => $googleUser['id'],
                    'name' => $googleUser['name'],
                    'email' => $googleUser['email'],
                    'profile_picture' => $googleUser['picture'] ?? null
                ],
                'requires_signup' => true
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google authentication failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // Google Signup - Create new user with Google data
    public function googleSignup(Request $request)
    {
        try {
            $request->validate([
                'google_id' => 'required|string',
                'name' => 'required|string',
                'email' => 'required|email',
                'profile_picture' => 'nullable|string'
            ]);

            // Check if Google ID already exists
            $existingUser = User::where('google_id', $request->google_id)->first();
            if ($existingUser) {
                return response()->json([
                    'message' => 'Google account already registered',
                    'error' => 'ACCOUNT_EXISTS'
                ], 409);
            }

            // Check if email already exists
            $emailUser = User::where('email', $request->email)->first();
            if ($emailUser) {
                return response()->json([
                    'message' => 'Email already registered',
                    'error' => 'EMAIL_EXISTS'
                ], 409);
            }

            // Create new user with Google data
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'email_verified_at' => now(), // Google verified users
                'google_id' => $request->google_id,
                'profile_image' => $request->profile_picture,
                'is_seller' => false,
                'password' => Hash::make(uniqid()), // Random password
            ]);

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            \Log::info('Created new user with Google signup:', [
                'user_id' => $user->id,
                'google_id' => $request->google_id,
                'name' => $request->name,
                'email' => $request->email
            ]);

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
                'is_new_user' => true
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google signup failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
