<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerController extends Controller
{
    /**
     * Authenticated user becomes a seller with profile
     */
    public function becomeSeller(Request $request)
    {
        $user = Auth::user();

        if ($user->is_seller) {
            return response()->json(['message' => 'Already a seller'], 400);
        }

        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:50',
        ]);

        // ✅ Create seller profile linked to user
        $seller = Seller::create([
            'user_id'    => $user->id,
            'shop_name'  => $validated['shop_name'],
            'description'=> $validated['description'] ?? null,
            'address'    => $validated['address'] ?? null,
            'contact'    => $validated['contact'] ?? null,
        ]);

        // ✅ Mark user as seller
        $user->is_seller = 1;
        $user->save();

        return response()->json([
            'message' => 'You are now a seller!',
            'seller'  => $seller
        ], 201);
    }

    /**
     * Get seller's own profile
     */
    public function profile()
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json(['message' => 'You are not a seller'], 403);
        }

        $seller = $user->seller; // relation from User model

        return response()->json([
            'user'   => $user,
            'seller' => $seller
        ]);
    }
}
