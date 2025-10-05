<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductVerificationController extends Controller
{
    /**
     * Display a listing of products pending verification.
     */
    public function index(Request $request)
    {
        $query = Product::with(['user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to pending products
            $query->where('status', 'pending');
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return response()->json($products);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, Product $product)
    {
        return response()->json($product->load(['user']));
    }

    /**
     * Approve a product.
     */
    public function approve(Request $request, Product $product)
    {
        $actor = $request->user();

        if (!($actor instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);

        return response()->json($product->load(['user']));
    }

    /**
     * Reject a product.
     */
    public function reject(Request $request, Product $product)
    {
        $actor = $request->user();

        if (!($actor instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);

        return response()->json($product->load(['user']));
    }

    /**
     * Get product verification statistics.
     */
    public function stats(Request $request)
    {
        $stats = [
            'total' => Product::count(),
            'pending' => Product::where('status', 'pending')->count(),
            'approved' => Product::where('status', 'approved')->count(),
            'rejected' => Product::where('status', 'rejected')->count(),
        ];

        return response()->json($stats);
    }
}
