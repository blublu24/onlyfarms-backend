<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * List products (public).
     */
    public function index(Request $request)
    {
        $query = Product::with('seller'); // Eager-load seller

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where('product_name', 'like', '%' . $request->search . '%');
        }

        $products = $query->get()->map(function ($p) {
            return [
                'product_id' => $p->product_id,
                'product_name' => $p->product_name,
                'image_url' => $p->image_url ? asset('storage/' . $p->image_url) : null,
                'price' => $p->price,
                'description' => $p->description,
                'category' => $p->category,
                'seller_name' => $p->seller?->shop_name ?? 'Unknown Seller', // ✅ shop_name from sellers table
                'seller_id' => $p->seller_id,
            ];
        });

        return response()->json([
            'message' => 'Products fetched successfully',
            'data' => $products
        ]);
    }

    /**
     * Show a single product.
     */
    public function show($id)
    {
        $product = Product::with('user')->findOrFail($id);
        $product->full_image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

        return response()->json([
            'message' => 'Product fetched successfully',
            'data' => $product
        ]);
    }

    /**
     * Create a product (seller only).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('image_url')) {
            $path = $request->file('image_url')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        $validated['seller_id'] = $user->id;

        $product = Product::create($validated);
        $product->full_image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Update a product (seller only).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        if ($product->seller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('image_url')) {
            // 🔥 Delete old image if exists
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }

            $path = $request->file('image_url')->store('products', 'public');
            $validated['image_url'] = $path;
        } elseif ($request->has('image_url') && $request->image_url === "") {
            // If explicitly cleared
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }
            $validated['image_url'] = null;
        }

        $product->update($validated);
        $product->full_image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

        // ✅ Match AdminProductController response format
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Delete a product (seller only).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        if ($product->seller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // 🔥 Delete product image if exists
        if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * List current seller’s products.
     */
    public function myProducts()
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json(['error' => 'Only sellers can view their products'], 403);
        }

        $products = Product::where('seller_id', $user->id)->get()->map(function ($p) {
            $p->full_image_url = $p->image_url ? asset('storage/' . $p->image_url) : null;
            return $p;
        });

        return response()->json([
            'message' => 'My products fetched successfully',
            'data' => $products
        ]);
    }

    /**
     * 🔥 Admin: Get products by user (for admin-user-products page).
     */
    public function getUserProducts($sellerId)
    {
        $products = Product::where('seller_id', $sellerId)->get()->map(function ($p) {
            $p->full_image_url = $p->image_url ? asset('storage/' . $p->image_url) : null;
            return $p;
        });

        return response()->json([
            'message' => 'User products fetched successfully',
            'data' => $products
        ]);
    }
}
