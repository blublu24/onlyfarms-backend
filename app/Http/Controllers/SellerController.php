<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerController extends Controller
{
    // List all sellers
    public function index()
    {
        $sellers = Seller::with('user')->get();
        return response()->json($sellers);
    }

    // Create new seller profile
    public function store(Request $request)
    {
        $request->validate([
            'shop_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'business_permit' => 'nullable|string|max:255',
        ]);

        $seller = Seller::create([
            'user_id' => Auth::id(), // current logged in user
            'shop_name' => $request->shop_name,
            'address' => $request->address,
            'phone_number' => $request->phone_number,
            'business_permit' => $request->business_permit,
        ]);

        return response()->json([
            'message' => 'Seller profile created successfully',
            'seller' => $seller
        ], 201);
    }

    // View single seller
    public function show($id)
    {
        $seller = Seller::with('user')->findOrFail($id);
        return response()->json($seller);
    }

    // Update seller profile
    public function update(Request $request, $id)
    {
        $seller = Seller::findOrFail($id);

        // Optional: check if current user owns this seller profile
        if ($seller->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $seller->update($request->only([
            'shop_name',
            'address',
            'phone_number',
            'business_permit'
        ]));

        return response()->json([
            'message' => 'Seller profile updated',
            'seller' => $seller
        ]);
    }

    // Delete seller profile
    public function destroy($id)
    {
        $seller = Seller::findOrFail($id);

        if ($seller->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $seller->delete();

        return response()->json(['message' => 'Seller profile deleted']);
    }
}
