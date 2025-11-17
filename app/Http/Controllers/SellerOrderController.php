<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Notification;
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

        // Get the actual Seller ID (not User ID) for this user
        $seller = $user->seller;
        if (!$seller) {
            return response()->json(['message' => 'Seller profile not found'], 404);
        }

        // ✅ FIX: Use USER ID because order_items.seller_id stores the User ID, not Seller model ID
        // Fetch orders containing this seller's products using the USER ID
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
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'user_is_seller' => $user->is_seller,
            'orders_count' => $orders->count(),
            'orders' => $orders->toArray()
        ]);

        // Also check what order_items exist for this seller (using USER ID)
        $orderItems = \App\Models\OrderItem::where('seller_id', $user->id)->get();
        Log::info('Order items for seller', [
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'order_items_count' => $orderItems->count(),
            'order_items' => $orderItems->toArray()
        ]);

        // Check if user has products
        $userProducts = \App\Models\Product::where('seller_id', $user->id)->get();
        Log::info('User products', [
            'user_id' => $user->id,
            'seller_id' => $seller->id,
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
                'user_id' => $user->id,
                'seller_id' => $seller->id,
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

        // Get the actual Seller ID (not User ID) for this user
        $seller = $user->seller;
        if (!$seller) {
            return response()->json(['message' => 'Seller profile not found'], 404);
        }

        // ✅ FIX: Use USER ID because order_items.seller_id stores the User ID, not Seller model ID
        $order = Order::with(['items' => function ($q) use ($user) {
            $q->where('seller_id', $user->id);
        }, 'user', 'address'])->findOrFail($orderId);

        // Check if seller actually has items in this order
        if ($order->items->isEmpty()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Order fetched successfully',
            'data' => $order
        ]);
    }

    /**
     * Update status of an order (only for seller's own items)
     */
    public function updateStatus(Request $request, $orderId)
    {
        $user = $request->user();
        
        if (!$user->is_seller) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get the actual Seller ID (not User ID) for this user
        $seller = $user->seller;
        if (!$seller) {
            return response()->json(['message' => 'Seller profile not found'], 404);
        }

        $order = Order::with('items')->findOrFail($orderId);

        // ✅ FIX: Use USER ID because order_items.seller_id stores the User ID, not Seller model ID
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

        // Create notification for buyer when order is marked as delivered
        if ($newStatus === 'delivered' || $newStatus === 'completed') {
            try {
                $seller = $user->seller;
                $sellerName = $seller ? ($seller->shop_name ?? $seller->business_name ?? $user->name) : $user->name;
                
                Notification::create([
                    'user_id' => $order->user_id,
                    'type' => 'order',
                    'title' => 'Order Delivered! 🚚',
                    'message' => "Your order #{$order->id} has been delivered by {$sellerName}. Thank you for your purchase!",
                    'data' => [
                        'order_id' => $order->id,
                        'orderId' => $order->id,
                        'status' => 'completed',
                        'seller_name' => $sellerName,
                        'redirect_route' => '/FinalReceiptPage',
                        'redirect_params' => ['orderId' => $order->id],
                    ],
                    'is_read' => false,
                ]);

                Log::info('Notification created for buyer on order delivery', [
                    'order_id' => $order->id,
                    'buyer_user_id' => $order->user_id,
                    'seller_user_id' => $user->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create notification for buyer on order delivery', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the status update if notification fails
            }
        }

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

    /**
     * Seller: Confirm order (accept order and start preparing)
     */
    public function sellerConfirm(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items')->findOrFail($id);

        // Ensure the order has at least one item from this seller
        $hasSellerItems = $order->items()->where('seller_id', $user->id)->exists();
        if (!$hasSellerItems) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order can be confirmed
        if ($order->status === 'completed') {
            return response()->json(['message' => 'Order already completed'], 400);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Cannot confirm a cancelled order'], 400);
        }

        // 🔒 VALIDATE STOCK BEFORE CONFIRMING: Check if we have enough stock
        foreach ($order->items as $item) {
            if ($item->seller_id === $user->id) {
                $product = Product::where('product_id', $item->product_id)->first();
                if ($product) {
                    // Use actual weight if set, otherwise estimated weight or quantity
                    $requiredWeight = $item->actual_weight_kg ?? $item->estimated_weight_kg ?? $item->quantity;
                    
                    // Check total stock
                    $currentTotalStock = (float) $product->stock_kg;
                    if ($currentTotalStock < $requiredWeight) {
                        return response()->json([
                            'message' => "Insufficient total stock for product '{$product->product_name}'. Available: {$currentTotalStock}kg, Requested: {$requiredWeight}kg.",
                            'insufficient_stock' => true,
                            'product_id' => $product->product_id,
                            'product_name' => $product->product_name,
                            'available_stock' => $currentTotalStock,
                            'requested_quantity' => $requiredWeight
                        ], 422);
                    }
                    
                    // If item has a variation, also check variation-specific stock
                    if ($item->variation_type) {
                        $currentVariationStock = 0;
                        $variationLabel = '';
                        
                        switch ($item->variation_type) {
                            case 'premium':
                                $currentVariationStock = (float) $product->premium_stock_kg;
                                $variationLabel = 'Premium';
                                break;
                            case 'type_a':
                            case 'typeA':
                                $currentVariationStock = (float) $product->type_a_stock_kg;
                                $variationLabel = 'Type A';
                                break;
                            case 'type_b':
                            case 'typeB':
                                $currentVariationStock = (float) $product->type_b_stock_kg;
                                $variationLabel = 'Type B';
                                break;
                        }
                        
                        if ($currentVariationStock < $requiredWeight) {
                            return response()->json([
                                'message' => "Insufficient {$variationLabel} stock for product '{$product->product_name}'. Available: {$currentVariationStock}kg, Requested: {$requiredWeight}kg.",
                                'insufficient_stock' => true,
                                'product_id' => $product->product_id,
                                'product_name' => $product->product_name,
                                'variation_type' => $item->variation_type,
                                'available_stock' => $currentVariationStock,
                                'requested_quantity' => $requiredWeight
                            ], 422);
                        }
                    }
                }
            }
        }

        // Update order status to confirmed/preparing
        $order->status = 'confirmed';
        $order->save();

        // 🔒 DECREMENT STOCK: Only now that seller has confirmed the order
        foreach ($order->items as $item) {
            if ($item->seller_id === $user->id) {
                $product = Product::where('product_id', $item->product_id)->first();
                if ($product) {
                    // Use actual weight if set by seller, otherwise use estimated weight or quantity
                    $weightToDecrement = $item->actual_weight_kg ?? $item->estimated_weight_kg ?? $item->quantity;
                    
                    // Decrement total stock
                    $currentTotalStock = (float) $product->stock_kg;
                    $newTotalStock = max(0, $currentTotalStock - $weightToDecrement);
                    
                    // Prepare update array
                    $updateData = ['stock_kg' => $newTotalStock];
                    
                    // If item has a variation, also decrement the variation-specific stock
                    if ($item->variation_type) {
                        $variationStockField = null;
                        $currentVariationStock = 0;
                        
                        switch ($item->variation_type) {
                            case 'premium':
                                $variationStockField = 'premium_stock_kg';
                                $currentVariationStock = (float) $product->premium_stock_kg;
                                break;
                            case 'type_a':
                            case 'typeA':
                                $variationStockField = 'type_a_stock_kg';
                                $currentVariationStock = (float) $product->type_a_stock_kg;
                                break;
                            case 'type_b':
                            case 'typeB':
                                $variationStockField = 'type_b_stock_kg';
                                $currentVariationStock = (float) $product->type_b_stock_kg;
                                break;
                        }
                        
                        if ($variationStockField) {
                            $newVariationStock = max(0, $currentVariationStock - $weightToDecrement);
                            $updateData[$variationStockField] = $newVariationStock;
                            
                            Log::info('Variation stock decremented after seller confirmation', [
                                'product_id' => $product->product_id,
                                'product_name' => $product->product_name,
                                'variation_type' => $item->variation_type,
                                'variation_stock_field' => $variationStockField,
                                'old_variation_stock' => $currentVariationStock,
                                'new_variation_stock' => $newVariationStock,
                                'weight_decremented' => $weightToDecrement,
                            ]);
                        }
                    }
                    
                    // Increment total_sold field
                    $currentTotalSold = (float) $product->total_sold;
                    $newTotalSold = $currentTotalSold + $weightToDecrement;
                    $updateData['total_sold'] = $newTotalSold;
                    
                    // Update product with both total and variation stocks AND total_sold
                    $product->update($updateData);
                    
                    // Refresh product model to get updated values
                    $product->refresh();
                    
                    // Broadcast stock and sales updates to frontend via Reverb
                    try {
                        broadcast(new \App\Events\ProductStockUpdated($product));
                        broadcast(new \App\Events\ProductSalesUpdated($product));
                    } catch (\Exception $e) {
                        Log::warning('Failed to broadcast product updates', [
                            'error' => $e->getMessage(),
                            'product_id' => $product->product_id
                        ]);
                    }
                    
                    Log::info('Stock decremented and sales updated after seller confirmation', [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'old_total_stock' => $currentTotalStock,
                        'new_total_stock' => $newTotalStock,
                        'old_total_sold' => $currentTotalSold,
                        'new_total_sold' => $newTotalSold,
                        'weight_decremented' => $weightToDecrement,
                        'has_variation' => !empty($item->variation_type),
                        'variation_type' => $item->variation_type,
                        'order_id' => $order->id,
                        'seller_id' => $user->id
                    ]);
                }
            }
        }

        // Create notification for the buyer when seller confirms
        try {
            $seller = $user->seller;
            $sellerName = $seller ? ($seller->shop_name ?? $seller->business_name ?? $user->name) : $user->name;
            
            Notification::create([
                'user_id' => $order->user_id,
                'type' => 'order',
                'title' => 'Order Confirmed! ✅',
                'message' => "Your order #{$order->id} has been confirmed by {$sellerName} and is being prepared!",
                'data' => [
                    'order_id' => $order->id,
                    'orderId' => $order->id,
                    'status' => 'confirmed',
                    'seller_name' => $sellerName,
                    'redirect_route' => '/tabs/FinalReceiptPage',
                    'redirect_params' => ['orderId' => $order->id],
                ],
                'is_read' => false,
            ]);

            Log::info('Notification created for buyer on order confirmation', [
                'order_id' => $order->id,
                'buyer_user_id' => $order->user_id,
                'seller_user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create notification for buyer on order confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the confirmation if notification fails
        }

        return response()->json([
            'message' => 'Order confirmed successfully',
            'order' => $order->load('items')
        ]);
    }

    /**
     * Seller: Verify order (verify payment and details before processing)
     */
    public function verifyOrder(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items')->findOrFail($id);

        // ✅ CORRECT: Already using USER ID
        $hasSellerItems = $order->items()->where('seller_id', $user->id)->exists();
        if (!$hasSellerItems) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate request
        $request->validate([
            'verified' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        // Update verification status
        $order->seller_verified = $request->verified;
        $order->seller_verified_at = $request->verified ? now() : null;
        $order->seller_notes = $request->notes;
        
        if ($request->verified) {
            $order->status = 'verified';
        }
        
        $order->save();

        return response()->json([
            'message' => $request->verified ? 'Order verified successfully' : 'Order verification removed',
            'order' => $order->load('items')
        ]);
    }

    /**
     * Get pending orders for a seller
     */
    public function pendingOrders(Request $request)
    {
        $user = $request->user();

        if (!$user->is_seller) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Fetch pending orders containing this seller's products
        $orders = Order::with(['items' => function ($q) use ($user) {
            $q->where('seller_id', $user->id);
        }])
            ->whereHas('items', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Pending orders fetched successfully',
            'data' => $orders
        ]);
    }
}
