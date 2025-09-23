<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerController extends Controller
{
    // ✅ Become a seller
    public function becomeSeller(Request $request)
    {
        $request->validate([
            'shop_name'       => 'required|string|max:255',
            'address'         => 'nullable|string|max:255',
            'phone_number'    => 'nullable|string|max:50',
            'business_permit' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        if ($user->is_seller) {
            return response()->json([
                'message' => 'You are already a seller.',
                'user'    => $user->load('seller')
            ], 400);
        }

        // Create Seller profile
        $seller = Seller::create([
            'user_id'        => $user->id,
            'shop_name'      => $request->shop_name,
            'address'        => $request->address,
            'phone_number'   => $request->phone_number,
            'business_permit'=> $request->business_permit,
        ]);

        // Mark user as seller
        $user->is_seller = true;
        $user->save();

        return response()->json([
            'message' => 'You are now a seller!',
            'user'    => $user->load('seller')
        ], 201);
    }

    // ✅ Seller’s own profile
    public function profile()
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json([
                'message' => 'You are not a seller.'
            ], 403);
        }

        return response()->json([
            'user'   => $user->load('seller'),
            'seller' => $user->seller
        ]);
    }

    // ✅ Public: list all sellers
    public function index()
    {
        $sellers = Seller::with('user')->get();
        return response()->json($sellers);
    }

    // ✅ Public: show specific seller
    public function show($id)
    {
        $seller = Seller::with('user', 'products')->findOrFail($id);
        return response()->json($seller);
    }
}
