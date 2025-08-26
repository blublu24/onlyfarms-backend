<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    // Public: show all products
    public function index()
    {
        $products = Product::with('user')->get(); // load seller info
        return response()->json($products);
    }

    // Public: show single product
    public function show($id)
    {
        $product = Product::with('user')->findOrFail($id);
        return response()->json($product);
    }

    // Protected: store product (only for sellers)
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->is_seller) {
            return response()->json(['error' => 'Only sellers can create products'], 403);
        }

        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|string',
        ]);

        $product = Product::create([
            'product_name' => $validated['product_name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'image_url' => $validated['image_url'] ?? null,
            'seller_id' => $user->id,
        ]);

        return response()->json(['message' => 'Product added successfully', 'product' => $product], 201);
    }

    // Protected: update product (seller can only update own)
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        if (!$user || !$user->is_seller || $product->seller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'image_url' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json(['message' => 'Product updated', 'product' => $product]);
    }

    // Protected: delete product (seller can only delete own)
    public function destroy($id)
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        if (!$user || !$user->is_seller || $product->seller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    // Protected: show authenticated seller's own products
    public function myProducts()
    {
        $user = Auth::user();

        if (!$user || !$user->is_seller) {
            return response()->json(['error' => 'Only sellers can view their products'], 403);
        }

        $products = Product::where('seller_id', $user->id)->get();
        return response()->json($products);
    }
}
