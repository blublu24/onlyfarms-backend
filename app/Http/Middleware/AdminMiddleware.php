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

        // Check if user exists and is an Admin instance
        if (!$user || !($user instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized: Admins only'], 403);
        }

        return $next($request);
    }
}
