<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class SellerOrderController extends Controller
{
    /**
     * Get all orders that include this seller's products,
     * grouped by order status (pending, delivering, delivered)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->is_seller) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Fetch orders containing this seller's products
        $orders = Order::with(['items' => function ($q) use ($user) {
            $q->where('seller_id', $user->id);
        }])
            ->whereHas('items', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Group orders by status into array structure
        $grouped = $orders->groupBy('status')->map(function ($orders, $status) {
            return [
                'status' => $status,
                'orders' => $orders->values(),
            ];
        })->values();

        return response()->json($grouped);
    }

    /**
     * Show details of a single order (only if it has seller's products)
     */
    public function show(Request $request, $orderId)
    {
        $user = $request->user();

        if (!$user->is_seller) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::with(['items' => function ($q) use ($user) {
            $q->where('seller_id', $user->id);
        }])->findOrFail($orderId);

        // Check if seller actually has items in this order
        if ($order->items->isEmpty()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order);
    }

    /**
     * Update status of an order (only for seller's own items)
     */
    public function updateStatus(Request $request, $orderId)
    {
        $user = $request->user();
        $order = Order::with('items')->findOrFail($orderId);

        // Ensure the order has at least one item from this seller
        $item = $order->items()->where('seller_id', $user->id)->first();
        if (!$item) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,delivering,delivered'
        ]);

        // ⚠️ Note: This updates the WHOLE order's status.
        // If you want per-item statuses, you need a status column in order_items.
        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->load('items')
        ]);
    }
}
