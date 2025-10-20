<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

class SocialLoginController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            return $this->handleSocialUser($googleUser, 'google');
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Redirect to Facebook OAuth
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();
            
            return $this->handleSocialUser($facebookUser, 'facebook');
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Facebook login failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle social user authentication/registration
     */
    private function handleSocialUser($socialUser, $provider)
    {
        try {
            // Check if user already exists
            $existingUser = User::where('email', $socialUser->getEmail())->first();

            if ($existingUser) {
                // User exists, log them in
                $token = $existingUser->createToken('onlyfarms_token')->plainTextToken;
                
                return response()->json([
                    'message' => 'Login successful!',
                    'user' => $existingUser,
                    'token' => $token,
                    'provider' => $provider
                ]);
            } else {
                // Create new user
                $newUser = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(Str::random(24)), // Random password for social users
                    'email_verified_at' => now(), // Social users are pre-verified
                    'social_provider' => $provider,
                    'social_id' => $socialUser->getId(),
                    'profile_image' => $socialUser->getAvatar(),
                ]);

                $token = $newUser->createToken('onlyfarms_token')->plainTextToken;

                return response()->json([
                    'message' => 'Account created successfully!',
                    'user' => $newUser,
                    'token' => $token,
                    'provider' => $provider,
                    'is_new_user' => true
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get social login URLs for frontend
     */
    public function getSocialUrls()
    {
        return response()->json([
            'google_url' => Socialite::driver('google')->redirect()->getTargetUrl(),
            'facebook_url' => Socialite::driver('facebook')->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle mobile social login (for React Native)
     */
    public function mobileGoogleLogin(Request $request)
    {
        $validator = $request->validate([
            'id_token' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
            'profile_image' => 'nullable|string',
        ]);

        try {
            // Verify Google ID token (you can add verification here)
            // For now, we'll trust the mobile app's verification
            
            $googleUser = (object) [
                'id' => $request->id_token, // Using id_token as ID
                'name' => $request->name,
                'email' => $request->email,
                'avatar' => $request->profile_image,
            ];

            return $this->handleSocialUser($googleUser, 'google_mobile');
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Mobile Google login failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle mobile Facebook login (for React Native)
     */
    public function mobileFacebookLogin(Request $request)
    {
        $validator = $request->validate([
            'facebook_id' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
            'profile_image' => 'nullable|string',
        ]);

        try {
            $facebookUser = (object) [
                'id' => $request->facebook_id,
                'name' => $request->name,
                'email' => $request->email,
                'avatar' => $request->profile_image,
            ];

            return $this->handleSocialUser($facebookUser, 'facebook_mobile');
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Mobile Facebook login failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
