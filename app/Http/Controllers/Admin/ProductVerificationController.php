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

        $products = $query->orderBy('created_at', 'desc')->get()->map(function ($product) {
            $imageUrl = $product->full_image_url; // ✅ Use model method for full URL
            
            return [
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'description' => $product->description,
                'price_per_kg' => $product->price_per_kg,
                'stock_kg' => $product->stock_kg,
                'available_units' => $product->available_units,
                'image_url' => $imageUrl, // Full URL
                'status' => $product->status,
                'created_at' => $product->created_at,
                'approved_at' => $product->approved_at,
                'approved_by' => $product->approved_by,
                'user' => $product->user,
            ];
        });

        return response()->json($products);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, Product $product)
    {
        $productData = $product->load(['user']);
        $imageUrl = $productData->full_image_url; // ✅ Use model method for full URL
        
        return response()->json([
            'product_id' => $productData->product_id,
            'product_name' => $productData->product_name,
            'description' => $productData->description,
            'price_per_kg' => $productData->price_per_kg,
            'stock_kg' => $productData->stock_kg,
            'available_units' => $productData->available_units,
            'image_url' => $imageUrl, // Full URL
            'status' => $productData->status,
            'created_at' => $productData->created_at,
            'approved_at' => $productData->approved_at,
            'approved_by' => $productData->approved_by,
            'user' => $productData->user,
        ]);
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

        $productData = $product->load(['user']);
        return response()->json([
            'product_id' => $productData->product_id,
            'product_name' => $productData->product_name,
            'description' => $productData->description,
            'price_per_kg' => $productData->price_per_kg,
            'stock_kg' => $productData->stock_kg,
            'available_units' => $productData->available_units,
            'image_url' => $productData->image_url, // This will use the model accessor
            'status' => $productData->status,
            'created_at' => $productData->created_at,
            'approved_at' => $productData->approved_at,
            'approved_by' => $productData->approved_by,
            'user' => $productData->user,
        ]);
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

        $productData = $product->load(['user']);
        return response()->json([
            'product_id' => $productData->product_id,
            'product_name' => $productData->product_name,
            'description' => $productData->description,
            'price_per_kg' => $productData->price_per_kg,
            'stock_kg' => $productData->stock_kg,
            'available_units' => $productData->available_units,
            'image_url' => $productData->image_url, // This will use the model accessor
            'status' => $productData->status,
            'created_at' => $productData->created_at,
            'approved_at' => $productData->approved_at,
            'approved_by' => $productData->approved_by,
            'user' => $productData->user,
        ]);
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
