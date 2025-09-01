<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    // Public: show all products (with optional search)
    public function index(Request $request)
    {
        $query = Product::with('user'); // base query with seller info

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('product_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        $products = $query->get();

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
        $request->validate([
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'seller_id' => 'required|exists:users,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            // Store file in "storage/app/public/products"
            $path = $request->file('image')->store('products', 'public');
            // Generate public URL
            $imageUrl = asset('storage/' . $path);
        }

        $product = Product::create([
            'product_name' => $request->product_name,
            'description' => $request->description,
            'price' => $request->price,
            'seller_id' => $request->seller_id,
            'image_url' => $imageUrl, // store URL not path
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
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
            'image_url' => 'nullable|string', // ✅ updated with only image_url
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated',
            'product' => $product
        ]);
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
