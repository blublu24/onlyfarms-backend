<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Admin;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user(); // Sanctum authenticated user

        // Check if user exists and is listed in the admins table
        if (!$user || !Admin::where('email', $user->email)->exists()) {
            return response()->json(['message' => 'Unauthorized: Admins only'], 403);
        }

        return $next($request);
    }
}
