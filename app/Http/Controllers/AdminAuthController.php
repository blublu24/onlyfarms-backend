<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin; // <-- use Admin model now

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Find admin by email in admins table
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create token using Sanctum with admin guard and set expiration
        $newToken = $admin->createToken('admin_token', ['admin']);
        // Set a concrete expiration (60 days) on the token model for extra safety
        $expiresAt = now()->addDays(60); // Facebook-style: 60 days expiration
        $accessToken = $newToken->accessToken;
        $accessToken->expires_at = $expiresAt;
        $accessToken->save();

        return response()->json([
            'admin' => $admin,
            'token' => $newToken->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }
}