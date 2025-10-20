<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product; // ✅ Import Product model
use App\Models\Order;   // ✅ Import Order model
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function products($id)
    {
        $user = User::with('products')->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user->products);
    }

    // ✅ Update a specific product for a user
    public function updateProduct(Request $request, $userId, $productId)
    {
        $product = Product::where('user_id', $userId)->find($productId);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'price' => 'sometimes|numeric|min:0',
        ]);

        if ($request->has('name')) $product->name = $request->name;
        if ($request->has('description')) $product->description = $request->description;
        if ($request->has('price')) $product->price = $request->price;

        $product->save();

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    // No constructor needed for middleware, it's handled in api.php

    // 1. List all users
    public function index()
    {
        return response()->json(User::all());
    }

    // 2. Show a single user
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    // 3. Create a new user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'is_seller' => 'nullable|boolean'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_seller' => $request->is_seller ?? false
        ]);

        return response()->json($user, 201);
    }

    // 4. Update an existing user
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'is_seller' => 'nullable|boolean'
        ]);

        if ($request->name) $user->name = $request->name;
        if ($request->email) $user->email = $request->email;
        if ($request->password) $user->password = Hash::make($request->password);
        if (!is_null($request->is_seller)) $user->is_seller = $request->is_seller;

        $user->save();

        return response()->json($user);
    }

    // 5. Delete a user
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // 6. ✅ Admin: Get all orders for a specific user
    public function userOrders($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $orders = Order::with(['items.product', 'address'])
            ->where('user_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($order) {
                return [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'payment_method' => $order->payment_method,
                    'note' => $order->note,
                    'total' => $order->total,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'name' => $item->product->product_name ?? 'N/A',
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->price,
                        ];
                    }),
                    'buyer' => [
                        'name' => $order->address?->name ?? $order->user->name ?? 'N/A',
                        'phone' => $order->address?->phone ?? 'N/A',
                        'address' => $order->address?->address ?? 'N/A',
                    ],
                ];
            });

        return response()->json($orders);
    }
}