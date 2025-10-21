<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminSellerController extends Controller
{
    /**
     * Get all sellers with their status
     */
    public function index(Request $request)
    {
        $status = $request->query('status'); // pending, approved, rejected

        $query = Seller::with('user:id,name,email,phone_number');

        if ($status) {
            $query->where('status', $status);
        }

        $sellers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Sellers fetched successfully',
            'data' => $sellers
        ]);
    }

    /**
     * Get a specific seller application
     */
    public function show($id)
    {
        $seller = Seller::with('user:id,name,email,phone_number')->findOrFail($id);

        return response()->json([
            'message' => 'Seller details fetched successfully',
            'data' => $seller
        ]);
    }

    /**
     * Approve a seller application
     */
    public function approve($id)
    {
        $seller = Seller::findOrFail($id);

        if ($seller->status === 'approved') {
            return response()->json([
                'message' => 'Seller is already approved'
            ], 400);
        }

        $seller->status = 'approved';
        $seller->save();

        // Update user to be a seller
        $user = User::find($seller->user_id);
        if ($user) {
            $user->is_seller = true;
            $user->save();
        }

        return response()->json([
            'message' => 'Seller approved successfully',
            'data' => $seller->load('user')
        ]);
    }

    /**
     * Reject a seller application
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $seller = Seller::findOrFail($id);

        if ($seller->status === 'rejected') {
            return response()->json([
                'message' => 'Seller is already rejected'
            ], 400);
        }

        $seller->status = 'rejected';
        $seller->rejection_reason = $request->reason;
        $seller->save();

        // Make sure user is not marked as seller
        $user = User::find($seller->user_id);
        if ($user) {
            $user->is_seller = false;
            $user->save();
        }

        return response()->json([
            'message' => 'Seller rejected successfully',
            'data' => $seller->load('user')
        ]);
    }

    /**
     * Get pending seller count
     */
    public function pendingCount()
    {
        $count = Seller::where('status', 'pending')->count();

        return response()->json([
            'count' => $count
        ]);
    }
}

