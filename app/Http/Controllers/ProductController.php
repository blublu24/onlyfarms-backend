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
            // ✅ Get the image URL from the model (which handles storage/ prefix)
            $imageUrl = $p->image_url;
            
            return [
                'product_id' => $p->product_id,
                'product_name' => $p->product_name,
                'image_url' => $imageUrl, // ✅ Use model accessor
                'fixed_image_url' => $imageUrl, // ✅ Use model accessor
                'price_kg' => $p->price_kg,
                'price_bunches' => $p->price_bunches,
                'stocks' => $p->stocks,
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
        $imageUrl = $product->image_url; // ✅ Use model accessor
        $product->full_image_url = $imageUrl;
        $product->fixed_image_url = $imageUrl;

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

        // Pre-process the request data to handle empty strings
        $requestData = $request->all();
        if (isset($requestData['price_kg']) && $requestData['price_kg'] === '') {
            $requestData['price_kg'] = null;
        }
        if (isset($requestData['price_bunches']) && $requestData['price_bunches'] === '') {
            $requestData['price_bunches'] = null;
        }

        // Merge the processed data back into the request
        $request->merge($requestData);

        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'price_kg' => 'nullable|numeric|min:0',
            'price_bunches' => 'nullable|numeric|min:0',
            'stocks' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Ensure at least one price is provided
        $hasPrice = (!empty($validated['price_kg']) && $validated['price_kg'] > 0) ||
                    (!empty($validated['price_bunches']) && $validated['price_bunches'] > 0);
        
        if (!$hasPrice) {
            return response()->json(['error' => 'At least one price (price_kg or price_bunches) must be provided and greater than 0'], 422);
        }

        if ($request->hasFile('image_url')) {
            $path = $request->file('image_url')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        $validated['seller_id'] = $user->id;


        $product = Product::create($validated);
        $imageUrl = $product->image_url; // ✅ Use model accessor
        $product->full_image_url = $imageUrl;
        $product->fixed_image_url = $imageUrl;

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

        // Pre-process the request data to handle empty strings
        $requestData = $request->all();
        if (isset($requestData['price_kg']) && $requestData['price_kg'] === '') {
            $requestData['price_kg'] = null;
        }
        if (isset($requestData['price_bunches']) && $requestData['price_bunches'] === '') {
            $requestData['price_bunches'] = null;
        }

        // Merge the processed data back into the request
        $request->merge($requestData);

        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'price_kg' => 'nullable|numeric|min:0',
            'price_bunches' => 'nullable|numeric|min:0',
            'stocks' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Debug: Log what we received
        \Log::info('Product update request data:', $request->all());
        \Log::info('Validated data:', $validated);

        // Ensure at least one price is provided
        $hasPrice = (!empty($validated['price_kg']) && $validated['price_kg'] > 0) ||
                    (!empty($validated['price_bunches']) && $validated['price_bunches'] > 0);
        
        if (!$hasPrice) {
            return response()->json(['error' => 'At least one price (price_kg or price_bunches) must be provided and greater than 0'], 422);
        }


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
        $imageUrl = $product->image_url; // ✅ Use model accessor
        $product->full_image_url = $imageUrl;
        $product->fixed_image_url = $imageUrl;

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
            $imageUrl = $p->image_url; // ✅ Use model accessor
            $p->full_image_url = $imageUrl;
            $p->fixed_image_url = $imageUrl;
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
            $imageUrl = $p->image_url; // ✅ Use model accessor
            $p->full_image_url = $imageUrl;
            $p->fixed_image_url = $imageUrl;
            return $p;
        });

        return response()->json([
            'message' => 'User products fetched successfully',
            'data' => $products
        ]);
    }
}
