<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Events\OrderCreatedNotification;
use App\Models\Product;
use App\Models\Address;
use App\Models\Seller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class OrderController extends Controller
{
    /**
     * Buyer: Get logged-in user's orders
     */
    public function index(Request $request)
    {
        $orders = Order::with(['items.product', 'seller'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Orders fetched successfully',
            'data' => $orders
        ]);
    }

    /**
     * Buyer: View a single order
     */
    public function show(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load order with items and their seller information
        $order->load([
            'items' => function ($query) {
                $query->with(['seller' => function ($sellerQuery) {
                    $sellerQuery->select('id', 'shop_name', 'user_id');
                }]);
            }
        ]);

        return response()->json([
            'message' => 'Order fetched successfully',
            'data' => $order
        ]);
    }

    /**
     * Buyer: Place a new order
     */
    public function store(Request $request)
    {
        try {
            Log::info('Order creation started', ['user_id' => $request->user()->id]);

            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,product_id',
                'items.*.quantity' => 'required|numeric|min:0.01|max:999',
                'items.*.unit' => 'required|string|in:kg,bunches',
                'items.*.variation_type' => 'nullable|string',
                'items.*.variation_name' => 'nullable|string',
                'items.*.variation_price' => 'nullable|numeric|min:0',
                'address_id' => 'required|exists:addresses,address_id',
                'notes' => 'nullable|string|max:500',
                'payment_method' => 'nullable|string|in:cod,gcash,card',
            ]);

            $user = $request->user();

            // Ensure address belongs to this user
            $address = Address::where('address_id', $data['address_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            $deliveryAddress = $address->name . ', ' . $address->phone . ', ' . $address->address;

            return DB::transaction(function () use ($data, $user, $deliveryAddress) {
                $productIds = collect($data['items'])->pluck('product_id')->all();
                
                // Fetch products (no need to lock since we're not modifying stock yet)
                $products = Product::whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');

                $total = 0.0;
                $lineItems = [];

                // Note: Stock validation removed - it will be checked when seller confirms the order
                // This allows buyers to place orders even if stock is temporarily low,
                // and sellers can confirm or adjust based on actual availability

                // Calculate totals and prepare line items
                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (!$product)
                        abort(422, "Product {$row['product_id']} not found");
                    
                    $qty = (float) $row['quantity'];
                    $unit = $row['unit'];
                    
                    // Handle variation prices first
                    $unitPrice = 0;
                    $lineTotal = 0;
                    
                    // Debug logging for price calculation
                    Log::info('Price calculation debug', [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'row_data' => $row,
                        'has_variation_price' => isset($row['variation_price']),
                        'variation_price_value' => $row['variation_price'] ?? 'not_set',
                        'qty' => $qty,
                        'unit' => $unit
                    ]);
                    
                    // Check if this is a variation order (has variation_price)
                    if (isset($row['variation_price']) && $row['variation_price'] > 0) {
                        // For variations, use the total variation price
                        $lineTotal = (float) $row['variation_price'];
                        $unitPrice = $lineTotal / $qty; // Calculate price per unit
                        
                        Log::info('Using variation price', [
                            'variation_price' => $row['variation_price'],
                            'lineTotal' => $lineTotal,
                            'unitPrice' => $unitPrice
                        ]);
                    } else {
                        // Regular pricing based on unit
                        if ($unit === 'kg' && $product->price_kg) {
                            $unitPrice = (float) $product->price_kg;
                        } elseif ($unit === 'bunches' && $product->price_bunches) {
                            $unitPrice = (float) $product->price_bunches;
                        } else {
                            // Fallback to old price field if new pricing not available
                            $unitPrice = (float) $product->price ?? 0;
                        }
                        $lineTotal = round($unitPrice * $qty, 2);
                    }
                    
                    $total = round($total + $lineTotal, 2);

                    $raw = $product->image_url;
                    $fullImage = $raw && Str::startsWith($raw, ['http://', 'https://'])
                        ? $raw
                        : ($raw ? asset('storage/' . $raw) : null);

                    $lineItem = [
                        'product_id' => $product->product_id,
                        'seller_id' => $product->seller_id,
                        'product_name' => $product->product_name,
                        'price' => $lineTotal, // Use total price, not unit price
                        'quantity' => $qty,
                        'unit' => $unit,
                        'image_url' => $fullImage,
                        'estimated_weight_kg' => $qty,
                        'price_per_kg_at_order' => $unitPrice,
                        'variation_type' => $row['variation_type'] ?? null,
                        'variation_name' => $row['variation_name'] ?? null,
                        'estimated_price' => $lineTotal, // Add estimated price field
                    ];
                    
                    // Debug logging for line item creation
                    Log::info('Line item being created', [
                        'lineItem' => $lineItem,
                        'calculated_values' => [
                            'lineTotal' => $lineTotal,
                            'unitPrice' => $unitPrice,
                            'qty' => $qty
                        ]
                    ]);
                    
                    $lineItems[] = $lineItem;

                    // Debug logging for seller_id
                    Log::info('Order item created', [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'seller_id' => $product->seller_id,
                        'buyer_id' => $user->id
                    ]);
                }

                $orderData = [
                    'user_id' => $user->id,
                    'address_id' => $data['address_id'],
                    'total' => $total,
                    'status' => 'pending',
                    'delivery_address' => $deliveryAddress,
                    'note' => $data['notes'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cod',
                    'payment_link' => null,
                    'payment_status' => 'pending',
                ];

                $order = Order::create($orderData);

                foreach ($lineItems as $li) {
                    $order->items()->create($li);
                }

                $order->load(['items', 'user', 'items.product']);

                $itemsGroupedBySeller = $order->items->groupBy('seller_id');

                foreach ($itemsGroupedBySeller as $sellerId => $items) {
                    $seller = Seller::with('user')->find($sellerId);

                    if (!$seller || !$seller->user) {
                        continue;
                    }

                    $itemsPayload = $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'variation_type' => $item->variation_type,
                            'variation_name' => $item->variation_name,
                        ];
                    })->values()->all();

                    $firstItemName = $itemsPayload[0]['product_name'] ?? 'your products';

                    $payload = [
                        'type' => 'new_order',
                        'title' => 'New Order Received! 🛒',
                        'message' => "You have a new order for {$firstItemName}",
                        'order' => [
                            'id' => $order->id,
                            'status' => $order->status,
                            'total' => $order->total,
                            'created_at' => $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                            'delivery_address' => $order->delivery_address,
                            'item_count' => count($itemsPayload),
                            'items' => $itemsPayload,
                            'buyer' => [
                                'id' => $order->user->id,
                                'name' => $order->user->name,
                                'phone_number' => $order->user->phone_number,
                            ],
                        ],
                    ];

                    Notification::create([
                        'user_id' => $seller->user->id,
                        'type' => $payload['type'],
                        'title' => $payload['title'],
                        'message' => $payload['message'],
                        'data' => [
                            'order' => $payload['order'],
                            'redirect_route' => '/tabs/SellerConfirmOrderPage',
                            'redirect_params' => ['orderId' => $order->id],
                        ],
                    ]);

                    broadcast(new OrderCreatedNotification($seller->user->id, $payload))->toOthers();
                }

                // Note: Stock is NOT decremented here - it will only be decremented when seller accepts the order
                // This prevents stock from being locked up in pending orders
                Log::info('Order created successfully - stock will be decremented when seller accepts', [
                    'order_id' => $order->id,
                    'total_items' => count($data['items']),
                    'note' => 'Stock remains unchanged until seller confirmation'
                ]);

                // PayMongo checkout integration
                if ($order->payment_method !== 'cod') {
                    $amount = (int) ($order->total * 100); // in centavos
                    $client = new Client();

                    $response = $client->post('https://api.paymongo.com/v1/checkout_sessions', [
                        'headers' => [
                            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':'),
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'data' => [
                                'attributes' => [
                                    'line_items' => [
                                        [
                                            'currency' => 'PHP',
                                            'amount' => $amount,
                                            'name' => 'Order #' . $order->id,
                                            'quantity' => 1,
                                        ]
                                    ],
                                    'payment_method_types' => ['gcash', 'card'],
                                    'success_url' => url("/payments/success/{$order->id}"),
                                    'cancel_url' => url("/payments/cancel/{$order->id}"),
                                    'metadata' => [
                                        'order_id' => $order->id
                                    ],
                                ],
                            ],
                        ],
                    ]);

                    $result = json_decode($response->getBody(), true);
                    $checkoutUrl = $result['data']['attributes']['checkout_url'] ?? null;

                    if ($checkoutUrl) {
                        $order->payment_link = $checkoutUrl;
                        $order->save();
                    }
                }

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => $order->load('items')
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'message' => 'Order creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Seller: Get orders containing their products
     */
    public function sellerOrders(Request $request)
    {
        $seller = $request->user()->seller;
        if (!$seller)
            return response()->json(['message' => 'You are not a seller'], 403);

        $orders = Order::with(['items.product', 'user', 'address']) // <-- add 'address'
            ->whereHas('items', fn($q) => $q->where('seller_id', $seller->id))
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
                            'price' => $item->price, // This is the snapshot price from order_items
                            'price_kg' => $item->product->price_kg ?? null,
                            'price_bunches' => $item->product->price_bunches ?? null,
                        ];
                    }),
                    'buyer' => [
                        'name' => $order->address?->name ?? $order->user->name ?? 'N/A',
                        'phone' => $order->address?->phone ?? 'N/A',
                        'address' => $order->address?->address ?? 'N/A',
                    ],
                ];
            });
        return response()->json([
            'message' => 'Seller orders fetched successfully',
            'data' => $orders
        ]);
    }


    /**
     * Seller: Update order status manually
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('PayMongo-Signature');
        
        \Log::info('Raw PayMongo payload:', $payload);

        // 🔒 SECURITY: Verify webhook signature
        if (!$this->verifyWebhookSignature($signature, $request->getContent())) {
            \Log::warning('Invalid webhook signature', [
                'signature' => $signature,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Navigate the JSON exactly
        $eventData = $payload['data']['attributes'] ?? null;

        // Check if this is a payment.paid event
        if ($eventData && ($eventData['type'] ?? null) === 'payment.paid') {
            $paymentAttributes = $eventData['data']['attributes'] ?? null;

            $status = $paymentAttributes['status'] ?? null;
            $orderId = $paymentAttributes['metadata']['order_id'] ?? null;

            if ($status === 'paid' && $orderId) {
                $order = \App\Models\Order::find($orderId);
                if ($order) {
                    // 🔒 SECURITY: Additional validation
                    if ($order->payment_status === 'pending') {
                        $order->status = 'completed';
                        $order->payment_status = 'paid';
                        $order->save();

                        \Log::info("Order {$orderId} updated to paid/completed");
                    } else {
                        \Log::warning("Order {$orderId} already processed", [
                            'current_status' => $order->status,
                            'payment_status' => $order->payment_status
                        ]);
                    }
                } else {
                    \Log::warning("Order {$orderId} not found");
                }
            } else {
                \Log::warning("Webhook received but status not paid or orderId missing", [
                    'status' => $status,
                    'orderId' => $orderId
                ]);
            }
        } else {
            \Log::warning("Webhook received but not a payment.paid event", [
                'event_type' => $eventData['type'] ?? null
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Verify PayMongo webhook signature
     */
    private function verifyWebhookSignature($signature, $payload)
    {
        if (!$signature) {
            return false;
        }

        $webhookSecret = env('PAYMONGO_WEBHOOK_SECRET');
        if (!$webhookSecret) {
            \Log::warning('PayMongo webhook secret not configured');
            return false;
        }

        // Parse signature header (format: t=timestamp,v1=signature)
        $signatureData = [];
        foreach (explode(',', $signature) as $pair) {
            list($key, $value) = explode('=', $pair, 2);
            $signatureData[$key] = $value;
        }

        $timestamp = $signatureData['t'] ?? null;
        $signatureHash = $signatureData['v1'] ?? null;

        if (!$timestamp || !$signatureHash) {
            return false;
        }

        // Check timestamp (prevent replay attacks)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 300) { // 5 minutes tolerance
            \Log::warning('Webhook timestamp too old', [
                'timestamp' => $timestamp,
                'current_time' => $currentTime
            ]);
            return false;
        }

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $webhookSecret);
        
        return hash_equals($signatureHash, $expectedSignature);
    }

    /**
     * Handle payment failure and restore stock
     */
    public function handlePaymentFailure($orderId)
    {
        $order = Order::find($orderId);
        if ($order && $order->status !== 'cancelled') {
            $order->status = 'cancelled';
            $order->payment_status = 'failed';
            $order->save();

            // Restore stock for failed payment
            $this->restoreStockForOrder($order);
            
            Log::info('Order cancelled due to payment failure', [
                'order_id' => $orderId,
                'stock_restored' => true
            ]);
        }
    }

    /**
     * Restore stock for cancelled orders
     */
    private function restoreStockForOrder(Order $order)
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $currentStock = (float) $product->stock_kg;
                $newStock = $currentStock + $item->quantity;
                
                $product->update(['stock_kg' => $newStock]);
                
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


    // ✅ New private helper
    private function markAsCompleted(Order $order)
    {
        $order->status = 'completed';

        // If not yet marked as paid, update payment_status
        if ($order->payment_status === 'pending') {
            $order->payment_status = 'paid';
        }

        $order->save();
    }

    /**
     * Seller: Mark COD order as delivered
     */
    public function markCODDelivered($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->payment_method !== 'cod') {
            return response()->json(['message' => 'Not a COD order'], 400);
        }

        // ✅ Use the helper
        $this->markAsCompleted($order);

        return response()->json([
            'message' => 'The COD order has been delivered and marked as paid.',
            'order' => $order
        ]);
    }

    /**
     * Buyer: Confirm order receipt
     */
    public function buyerConfirm($id)
    {
        $order = Order::findOrFail($id);

        // Check authorization
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order can be confirmed
        if ($order->status === 'completed') {
            return response()->json(['message' => 'Order already completed'], 400);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Cannot confirm a cancelled order'], 400);
        }

        // Update order status
        $order->status = 'completed';
        
        // Mark as paid if COD
        if ($order->payment_method === 'cod' && $order->payment_status === 'pending') {
            $order->payment_status = 'paid';
        }
        
        $order->save();

        return response()->json([
            'message' => 'Order confirmed successfully',
            'order' => $order->load('items')
        ]);
    }

    /**
     * Cancel an order (buyer or seller)
     */
    public function cancelOrder($id)
    {
        $order = Order::findOrFail($id);

        // Check authorization (buyer or seller can cancel)
        $userId = auth()->id();
        $isBuyer = $order->user_id === $userId;
        $isSeller = $order->items->contains(function($item) use ($userId) {
            return $item->product && $item->product->seller_id === $userId;
        });

        if (!$isBuyer && !$isSeller) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order can be cancelled
        if ($order->status === 'completed') {
            return response()->json(['message' => 'Cannot cancel a completed order'], 400);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Order is already cancelled'], 400);
        }

        // Check if seller has already confirmed the order
        if ($order->status === 'confirmed' || $order->status === 'preparing' || $order->status === 'ready') {
            return response()->json(['message' => 'Cannot cancel order - seller has already confirmed and is preparing your order'], 400);
        }

        // Restore stock for all items
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('stock_kg', $item->quantity);
                
                Log::info('Stock restored due to order cancellation:', [
                    'order_id' => $order->id,
                    'product_id' => $product->product_id,
                    'quantity_restored' => $item->quantity,
                    'new_stock' => $product->stock_kg
                ]);
            }
        }

        // Update order status
        $order->status = 'cancelled';
        $order->save();

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order->load('items')
        ]);
    }

    /**
     * Update order item (quantity, price, etc.)
     */
    public function updateItem($orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);
        $item = $order->items()->findOrFail($itemId);

        // Check authorization (seller only)
        $product = Product::find($item->product_id);
        if (!$product || $product->seller_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate request
        $validated = request()->validate([
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string|in:pending,confirmed,preparing,ready,delivered',
            'actual_weight_kg' => 'sometimes|numeric|min:0',
            'seller_verification_status' => 'sometimes|string|in:pending,seller_accepted,seller_rejected',
        ]);

        // If quantity is being updated, adjust stock
        if (isset($validated['quantity'])) {
            $oldQuantity = $item->quantity;
            $newQuantity = $validated['quantity'];
            $difference = $newQuantity - $oldQuantity;

            // Check if enough stock
            if ($difference > 0 && $product->stock_kg < $difference) {
                return response()->json([
                    'message' => 'Insufficient stock',
                    'available_stock' => $product->stock_kg
                ], 400);
            }

            // Update stock
            $product->decrement('stock_kg', $difference);
            
            // Update item total
            $item->quantity = $newQuantity;
            $item->total = $newQuantity * $item->price;
        }

        // Update price if provided
        if (isset($validated['price'])) {
            $item->price = $validated['price'];
            $item->total = $item->quantity * $validated['price'];
        }

        // Update status if provided
        if (isset($validated['status'])) {
            $item->status = $validated['status'];
        }

        // Update actual weight if provided
        if (isset($validated['actual_weight_kg'])) {
            $item->actual_weight_kg = $validated['actual_weight_kg'];
        }

        // Update seller verification status if provided
        if (isset($validated['seller_verification_status'])) {
            $item->seller_verification_status = $validated['seller_verification_status'];
        }

        $item->save();

        // Recalculate order total
        $order->total = $order->items->sum('total');
        $order->save();

        return response()->json([
            'message' => 'Order item updated successfully',
            'item' => $item,
            'order' => $order->load('items')
        ]);
    }

}