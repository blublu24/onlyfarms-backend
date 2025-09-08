<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // GET /api/orders  (current user's orders)
    public function index(Request $request)
    {
        $orders = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // GET /api/orders/{order} (ensure it belongs to user)
    public function show(Order $order)
    {
        // ✅ Security guard: only owner can view
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load('items'));
    }

    // POST /api/orders
    // Body: { items: [{product_id, quantity}], delivery_address?, notes? }
    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,product_id',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'delivery_address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Merge duplicates (same product_id in cart)
        $grouped = collect($data['items'])
            ->groupBy('product_id')
            ->map(fn($rows) => ['product_id' => (int) $rows[0]['product_id'], 'quantity' => (int) $rows->sum('quantity')])
            ->values();

        return DB::transaction(function () use ($grouped, $user, $data) {
            $productIds = $grouped->pluck('product_id')->all();
            $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

            // Compute total using server-side prices
            $total = 0.0;
            $lineItems = [];

            foreach ($grouped as $row) {
                $product = $products[$row['product_id']] ?? null;
                if (!$product) {
                    abort(422, "Product {$row['product_id']} not found");
                }

                $qty = (int) $row['quantity'];
                $unitPrice = (float) $product->price;
                $lineTotal = round($unitPrice * $qty, 2);
                $total = round($total + $lineTotal, 2);

                // Build a full image URL snapshot
                $raw = $product->image_url;
                $fullImage = $raw && Str::startsWith($raw, ['http://', 'https://'])
                    ? $raw
                    : ($raw ? asset('storage/' . $raw) : null);

                $lineItems[] = [
                    'product_id' => $product->product_id,
                    'seller_id' => $product->seller_id,
                    'product_name' => $product->product_name,
                    'price' => $unitPrice,
                    'quantity' => $qty,
                    'image_url' => $fullImage,
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'pending',
                'delivery_address' => $data['delivery_address'] ?? null,
                'note' => $data['notes'] ?? null,
            ]);

            foreach ($lineItems as $li) {
                $order->items()->create($li);
            }

            return response()->json($order->load('items'), 201);
        });
    }
}
