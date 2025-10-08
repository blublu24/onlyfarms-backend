<?php
/**
 * AdminProductController.php
 *
 * Author: Arsalan Sheikh
 * @date: 2025-10-08
 * @description: Controller for managing products in the admin panel
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class AdminProductController extends Controller
{
    /**
     * Show a single product (for editing).
     */
    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $imageUrl = $product->image_url; // Use model accessor
        
        // Construct full URL for frontend
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $baseUrl = request()->getSchemeAndHttpHost();
            $imageUrl = $baseUrl . '/' . $imageUrl;
        }
        
        $product->image_url = $imageUrl; // Set full URL
        $product->full_image_url = $imageUrl;
        $product->fixed_image_url = $imageUrl;

        return response()->json($product);
    }

    /**
     * Update an existing product (with optional image).
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // ✅ Validate only the fields provided (matching ProductController)
        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'price_kg' => 'nullable|numeric|min:0',
            'price_bunches' => 'nullable|numeric|min:0',
            'stocks' => 'sometimes|numeric|min:0',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Ensure at least one price is provided
        $hasPrice = (!empty($validated['price_kg']) && $validated['price_kg'] > 0) ||
                    (!empty($validated['price_bunches']) && $validated['price_bunches'] > 0);
        
        if (!$hasPrice) {
            return response()->json(['error' => 'At least one price (price_kg or price_bunches) must be provided and greater than 0'], 422);
        }

        // Handle image upload first
        if ($request->hasFile('image_url')) {
            // Delete old image if exists
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }

            $path = $request->file('image_url')->store('products', 'public');
            $product->image_url = $path; // ✅ store only relative path
        }

        // Update fields dynamically
        foreach (['product_name', 'description', 'category', 'price_kg', 'price_bunches', 'stocks'] as $field) {
            if ($request->has($field)) {
                $product->$field = $request->$field;
            }
        }

        $product->save();
        $product->refresh(); // Get latest timestamps

        // Construct full URL for response
        $imageUrl = $product->image_url;
        
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $baseUrl = request()->getSchemeAndHttpHost();
            $imageUrl = $baseUrl . '/' . $imageUrl;
        }
        
        $product->image_url = $imageUrl;
        $product->full_image_url = $imageUrl;
        $product->fixed_image_url = $imageUrl;

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
