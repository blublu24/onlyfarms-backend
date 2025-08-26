<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerController extends Controller
{
    // Authenticated user becomes seller
    public function becomeSeller()
    {
        $user = Auth::user();

        if ($user->is_seller) {
            return response()->json(['message' => 'Already a seller'], 400);
        }

        $user->is_seller = 1;
        $user->save();

        return response()->json(['message' => 'You are now a seller!']);
    }

    // Get seller's own profile
    public function profile()
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json(['message' => 'You are not a seller'], 403);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_seller' => $user->is_seller,
        ]);
    }
}
