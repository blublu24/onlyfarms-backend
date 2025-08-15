<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    // GET /api/products  → list all products
    public function index()
    {
        // include seller name
        $products = Product::with(['user:id,name'])->latest()->get();

        return response()->json($products, 200);
    }

    // POST /api/products → create product (seller only)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|url',
            'user_id' => 'required|exists:users,id', // who is creating
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::find($request->user_id);

        // only sellers can post products (simple check for now)
        if (!$user->is_seller) {
            return response()->json(['message' => 'Only sellers can create products.'], 403);
        }

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image_url' => $request->image_url,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Product created!',
            'product' => $product
        ], 201);
    }
}
