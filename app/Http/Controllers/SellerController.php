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
            'shop_name'         => 'required|string|max:255',
            'address'           => 'required|string|max:500',
            'phone_number'      => 'required|string|max:50',
            'email'             => 'required|email|max:255',
            'registered_name'   => 'required|string|max:255',
            'business_name'     => 'required|string|max:255',
            'tin'               => 'required|string|max:50',
            'vat_status'        => 'required|in:vat_registered,non_vat_registered',
            'business_email'    => 'required|email|max:255',
            'business_phone'    => 'required|string|max:50',
            'government_id_type' => 'required|string|max:100',
            'government_id_front' => 'required|string',
            'government_id_back' => 'nullable|string',
        ]);

        $user = Auth::user();

        if ($user->is_seller) {
            return response()->json([
                'message' => 'You are already a seller.',
                'user'    => $user->load('seller')
            ], 400);
        }

        // Create Seller profile with all required fields
        $seller = Seller::create([
            'user_id'            => $user->id,
            'shop_name'          => $request->shop_name,
            'address'            => $request->address,
            'phone_number'       => $request->phone_number,
            'email'              => $request->email,
            'registered_name'    => $request->registered_name,
            'business_name'      => $request->business_name,
            'tin'                => $request->tin,
            'vat_status'         => $request->vat_status,
            'business_email'     => $request->business_email,
            'business_phone'     => $request->business_phone,
            'government_id_type' => $request->government_id_type,
            'government_id_front' => $request->government_id_front,
            'government_id_back' => $request->government_id_back,
            'status'             => 'pending', // Admin approval required
        ]);

        // Update user email and phone if provided
        if ($request->email) {
            $user->email = $request->email;
        }
        if ($request->phone_number) {
            $user->phone_number = $request->phone_number;
        }
        $user->save();

        return response()->json([
            'message' => 'Seller registration submitted successfully! Your application is pending admin approval.',
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
        return response()->json([
            'message' => 'Sellers fetched successfully',
            'data' => $sellers
        ]);
    }

    // ✅ Public: show specific seller
    public function show($id)
    {
        $seller = Seller::with('user', 'products')->findOrFail($id);
        return response()->json([
            'message' => 'Seller fetched successfully',
            'data' => $seller
        ]);
    }
}
