<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Debug logging
        Log::info('Seller orders query', [
            'seller_id' => $user->id,
            'user_is_seller' => $user->is_seller,
            'orders_count' => $orders->count(),
            'orders' => $orders->toArray()
        ]);

        // Also check what order_items exist for this seller
        $orderItems = \App\Models\OrderItem::where('seller_id', $user->id)->get();
        Log::info('Order items for seller', [
            'seller_id' => $user->id,
            'order_items_count' => $orderItems->count(),
            'order_items' => $orderItems->toArray()
        ]);

        // Check if user has products
        $userProducts = \App\Models\Product::where('seller_id', $user->id)->get();
        Log::info('User products', [
            'user_id' => $user->id,
            'products_count' => $userProducts->count(),
            'products' => $userProducts->pluck('product_id', 'product_name')->toArray()
        ]);

        // Check all orders and order_items in the system
        $allOrders = \App\Models\Order::count();
        $allOrderItems = \App\Models\OrderItem::count();
        Log::info('System totals', [
            'total_orders' => $allOrders,
            'total_order_items' => $allOrderItems
        ]);

        // Return flat array for easier frontend handling
        return response()->json([
            'message' => 'Seller orders fetched successfully',
            'data' => $orders->toArray(),
            'debug' => [
                'seller_id' => $user->id,
                'orders_count' => $orders->count(),
                'order_items_count' => $orderItems->count(),
                'products_count' => $userProducts->count()
            ]
        ]);
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
        }, 'user', 'address'])->findOrFail($orderId);

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
            'status' => 'required|in:pending,delivering,delivered,cancelled'
        ]);

        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Handle stock restoration for cancelled orders
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $this->restoreStockForOrder($order);
        }

        // ⚠️ Note: This updates the WHOLE order's status.
        // If you want per-item statuses, you need a status column in order_items.
        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->load('items')
        ]);
    }

    /**
     * Restore stock for cancelled orders
     */
    private function restoreStockForOrder(Order $order)
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $currentStock = (float) $product->stocks;
                $newStock = $currentStock + $item->quantity;
                
                $product->update(['stocks' => $newStock]);
                
                Log::info('Stock restored for cancelled order', [
                    'order_id' => $order->id,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'old_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'quantity_restored' => $item->quantity
                ]);
            }
        }
    }
}
