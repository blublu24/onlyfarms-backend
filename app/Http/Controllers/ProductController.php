<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * List products (public).
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where('product_name', 'like', '%' . $request->search . '%');
        }

        $products = $query->get()->map(function ($p) {
            $p->full_image_url = $p->image_url ? asset('storage/' . $p->image_url) : null;
            return $p;
        });

        return response()->json($products);
    }

    /**
     * Show a single product.
     */
    public function show($id)
    {
        $product = Product::with('user')->findOrFail($id);
        $product->full_image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

        return response()->json($product);
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
            'description'  => 'nullable|string',
            'category'     => 'nullable|string|max:255',
            'price'        => 'required|numeric|min:0',
            'unit'         => 'required|string|max:50',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        $validated['seller_id'] = $user->id;

        $product = Product::create($validated);
        $product->full_image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
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
            'description'  => 'nullable|string',
            'category'     => 'nullable|string|max:255',
            'price'        => 'sometimes|numeric|min:0',
            'unit'         => 'sometimes|string|max:50',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        $product->update($validated);
        $product->full_image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product,
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

        return response()->json($products);
    }
}
