<?php

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

        // ✅ Validate only the fields provided
        $request->validate([
            'product_name' => 'sometimes|required|string|max:255',
            'description'  => 'sometimes|nullable|string',
            'price'        => 'sometimes|required|numeric|min:0',
            'category'     => 'sometimes|nullable|string|max:100',
            'unit'         => 'sometimes|nullable|string|max:50',
            'image_url'    => 'sometimes|nullable|file|image|max:2048',
        ]);

        // Update fields dynamically
        foreach (['product_name', 'description', 'price', 'category', 'unit'] as $field) {
            if ($request->has($field)) {
                $product->$field = $request->$field;
            }
        }

        // Handle image upload
        if ($request->hasFile('image_url')) {
            // Delete old image if exists
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }

            $path = $request->file('image_url')->store('products', 'public');
            $product->image_url = $path; // ✅ store only relative path
        } elseif ($request->has('image_url') && $request->image_url === "") {
            // If explicitly cleared
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }
            $product->image_url = null;
        }

        $product->save();

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
