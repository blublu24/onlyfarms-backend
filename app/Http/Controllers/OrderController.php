<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Address;
use App\Models\OrderItem;
use App\Models\UnitConversion;
use App\Events\ProductStockUpdated;
use App\Events\ProductSalesUpdated;
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
        $orders = Order::with('items')
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

        return response()->json([
            'message' => 'Order fetched successfully',
            'data' => $order->load('items')
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
                'items.*.quantity' => 'required|integer|min:1|max:999',
                'items.*.unit' => 'required|string|in:kg,bunches',
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
                
                // 🔒 CRITICAL: Lock products for update to prevent race conditions
                $products = Product::whereIn('product_id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $total = 0.0;
                $lineItems = [];

                // First pass: Validate stock availability with locked products
                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (!$product)
                        abort(422, "Product {$row['product_id']} not found");

                    $qty = (int) $row['quantity'];
                    $unit = $row['unit'];
                    
                    // Calculate required stock in kg
                    $requiredStockKg = 0;
                    if ($unit === 'kg') {
                        $requiredStockKg = $qty; // Direct kg quantity
                    } else {
                        // Get vegetable slug and calculate weight from unit conversion
                        $vegetableSlug = $product->getVegetableSlug();
                        $standardWeightPerUnit = \App\Models\UnitConversion::getStandardWeight($vegetableSlug, $unit);
                        $requiredStockKg = $qty * $standardWeightPerUnit;
                    }
                    
                    // Check stock availability with fresh locked data
                    $currentStock = (float) $product->stock_kg;
                    if ($currentStock < $requiredStockKg) {
                        abort(422, "Insufficient stock for product '{$product->product_name}'. Available: {$currentStock}kg, Requested: {$requiredStockKg}kg");
                    }
                }

                // Second pass: Calculate totals and prepare line items
                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    $qty = (int) $row['quantity'];
                    $unit = $row['unit'];
                    
                    // Calculate estimated weight based on unit
                    $estimatedWeightKg = 0;
                    if ($unit === 'kg') {
                        $estimatedWeightKg = $qty; // Direct kg quantity
                    } else {
                        // Get vegetable slug and calculate weight from unit conversion
                        $vegetableSlug = $product->getVegetableSlug();
                        $standardWeightPerUnit = \App\Models\UnitConversion::getStandardWeight($vegetableSlug, $unit);
                        $estimatedWeightKg = $qty * $standardWeightPerUnit;
                    }
                    
                    // Determine price based on unit - use new multi-unit pricing system
                    $unitPrice = 0;
                    if ($product->price_per_kg && $product->available_units) {
                        $availableUnits = is_string($product->available_units) 
                            ? json_decode($product->available_units, true) 
                            : $product->available_units;
                        
                        if ($unit === 'kg') {
                            $unitPrice = (float) $product->price_per_kg;
                        } else {
                            // For other units, calculate price based on estimated weight
                            $unitPrice = (float) $product->price_per_kg * $estimatedWeightKg / $qty;
                        }
                    } else {
                        // Fallback to old pricing system
                        if ($unit === 'kg' && $product->price_kg) {
                            $unitPrice = (float) $product->price_kg;
                        } elseif ($unit === 'bunches' && $product->price_bunches) {
                            $unitPrice = (float) $product->price_bunches;
                        } else {
                            $unitPrice = (float) $product->price ?? 0;
                        }
                    }
                    
                    $lineTotal = round($unitPrice * $qty, 2);
                    $total = round($total + $lineTotal, 2);

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
                        'unit' => $unit,
                        'estimated_weight_kg' => round($estimatedWeightKg, 4),
                        'price_per_kg_at_order' => (float) $product->price_per_kg ?? 0,
                        'estimated_price' => $lineTotal,
                        'image_url' => $fullImage,
                    ];

                    // Debug logging for line item creation
                    Log::info('Line item created', [
                        'product_name' => $product->product_name,
                        'unit' => $unit,
                        'quantity' => $qty,
                        'estimated_weight_kg' => round($estimatedWeightKg, 4),
                        'estimated_price' => $lineTotal,
                        'price_per_kg_at_order' => (float) $product->price_per_kg ?? 0,
                    ]);

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

                // 🔒 ATOMIC: Decrement stock for each product using atomic operations
                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']];
                    $qty = (int) $row['quantity'];
                    $unit = $row['unit'];
                    
                    // Calculate required stock in kg (same logic as validation)
                    $requiredStockKg = 0;
                    if ($unit === 'kg') {
                        $requiredStockKg = $qty; // Direct kg quantity
                    } else {
                        // Get vegetable slug and calculate weight from unit conversion
                        $vegetableSlug = $product->getVegetableSlug();
                        $standardWeightPerUnit = \App\Models\UnitConversion::getStandardWeight($vegetableSlug, $unit);
                        $requiredStockKg = $qty * $standardWeightPerUnit;
                    }
                    
                    // Use atomic decrement to prevent race conditions
                    $oldStock = (float) $product->stock_kg;
                    $newStock = max(0, $oldStock - $requiredStockKg);
                    
                    // Atomic update with stock validation
                    $updated = Product::where('product_id', $product->product_id)
                        ->where('stock_kg', '>=', $requiredStockKg) // Double-check stock is still sufficient
                        ->update(['stock_kg' => $newStock]);
                    
                    if (!$updated) {
                        // Stock was insufficient - this should not happen due to locks, but safety check
                        throw new \Exception("Stock became insufficient during order processing for product {$product->product_name}");
                    }
                    
                    Log::info('Stock decremented atomically', [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                        'decremented_by' => $requiredStockKg,
                        'unit' => $unit,
                        'quantity_ordered' => $qty,
                        'order_id' => $order->id
                    ]);
                    
                    // Broadcast stock update via Reverb
                    event(new ProductStockUpdated($product->fresh()));
                }

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
                        
                        // Increment sold count for each product in the order
                        foreach ($order->items as $item) {
                            $product = Product::find($item->product_id);
                            if ($product) {
                                $product->increment('total_sold', $item->quantity);
                                // Broadcast sales update via Reverb
                                event(new ProductSalesUpdated($product->fresh()));
                            }
                        }
                        
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
                // Use estimated_weight_kg instead of quantity for proper stock restoration
                $stockToRestore = (float) $item->estimated_weight_kg;
                $newStock = $currentStock + $stockToRestore;
                
                $product->update(['stock_kg' => $newStock]);
                
                // Broadcast stock update via Reverb
                event(new ProductStockUpdated($product->fresh()));
                
                Log::info('Stock restored for cancelled order', [
                    'order_id' => $order->id,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'old_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'stock_restored_kg' => $stockToRestore,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit
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
            
            // Increment sold count for each product in the order
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('total_sold', $item->quantity);
                    // Broadcast sales update via Reverb
                    event(new ProductSalesUpdated($product->fresh()));
                }
            }
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
     * Create order with multi-unit support and seller verification
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'buyer_id' => 'required|integer',
            'seller_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.vegetable_slug' => 'required|string',
            'items.*.unit' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.1',
            'payment_method' => 'required|string|in:cod,online,wallet'
        ]);

        DB::beginTransaction();
        try {
            // Create order
            $order = Order::create([
                'user_id' => $request->buyer_id,
                'seller_id' => $request->seller_id,
                'status' => 'for_seller_verification',
                'total_price' => 0,
                'payment_method' => $request->payment_method
            ]);

            $totalEstimatedPrice = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) {
                    throw new \Exception("Product not found: {$item['product_id']}");
                }

                // Calculate estimated weight using unit conversion
                $estimatedWeight = UnitConversion::calculateEstimatedWeight(
                    $item['vegetable_slug'],
                    $item['unit'],
                    $item['quantity']
                );

                if ($estimatedWeight <= 0) {
                    throw new \Exception("Invalid unit conversion for {$item['vegetable_slug']} - {$item['unit']}");
                }

                // Check and reserve stock
                if (!$product->hasStockForWeight($estimatedWeight)) {
                    throw new \Exception("Insufficient stock for {$product->product_name}. Required: {$estimatedWeight}kg, Available: {$product->stock_kg}kg");
                }

                // Reserve stock
                $product->reserveStock($estimatedWeight);
                
                // Broadcast stock update
                event(new ProductStockUpdated($product->fresh()));

                // Calculate estimated price
                $estimatedPrice = $estimatedWeight * $product->price_per_kg;

                // Create order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'seller_id' => $request->seller_id,
                    'product_name' => $product->product_name,
                    'price' => $estimatedPrice,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'image_url' => $product->image_url,
                    'estimated_weight_kg' => $estimatedWeight,
                    'price_per_kg_at_order' => $product->price_per_kg,
                    'estimated_price' => $estimatedPrice,
                    'reserved' => true,
                    'seller_verification_status' => 'pending'
                ]);

                $totalEstimatedPrice += $estimatedPrice;
            }

            // Update order total
            $order->update(['total_price' => $totalEstimatedPrice]);

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully and sent for seller verification',
                'order_id' => $order->id,
                'status' => 'for_seller_verification',
                'total_estimated_price' => $totalEstimatedPrice
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get pending orders for seller verification
     */
    public function getPendingOrders($sellerId)
    {
        $orders = Order::with(['items.product', 'user'])
            ->where('seller_id', $sellerId)
            ->where('status', 'for_seller_verification')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Pending orders fetched successfully',
            'data' => $orders
        ]);
    }

    /**
     * Seller verifies order items with actual weights
     */
    public function sellerVerify(Request $request, $orderId)
    {
        $request->validate([
            'seller_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.order_item_id' => 'required|integer',
            'items.*.actual_weight_kg' => 'required|numeric|min:0.001',
            'items.*.seller_notes' => 'nullable|string',
            'action' => 'required|string|in:accept,reject'
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            if ($order->seller_id != $request->seller_id) {
                throw new \Exception('Unauthorized: You can only verify your own orders');
            }

            if ($order->status !== 'for_seller_verification') {
                throw new \Exception('Order is not in verification status');
            }

            foreach ($request->items as $item) {
                $orderItem = OrderItem::find($item['order_item_id']);
                if (!$orderItem || $orderItem->order_id != $orderId) {
                    throw new \Exception('Invalid order item');
                }

                $product = $orderItem->product;

                if ($request->action === 'reject') {
                    // Return estimated weight to stock
                    $product->releaseStock($orderItem->estimated_weight_kg);
                    event(new ProductStockUpdated($product->fresh()));

                    $orderItem->update([
                        'reserved' => false,
                        'seller_verification_status' => 'seller_rejected',
                        'seller_notes' => $item['seller_notes'] ?? null
                    ]);
                    continue;
                }

                // Accept action
                $actualWeight = floatval($item['actual_weight_kg']);
                $delta = $actualWeight - $orderItem->estimated_weight_kg;

                if ($delta < 0) {
                    // Less weight than estimated - add back difference to stock
                    $product->releaseStock(abs($delta));
                } elseif ($delta > 0) {
                    // More weight than estimated - try to deduct extra
                    if (!$product->hasStockForWeight($delta)) {
                        throw new \Exception("Insufficient stock for extra weight. Required: {$delta}kg, Available: {$product->stock_kg}kg for {$product->product_name}");
                    }
                    $product->reserveStock($delta);
                }

                // Update order item
                $orderItem->update([
                    'actual_weight_kg' => $actualWeight,
                    'reserved' => false,
                    'seller_verification_status' => 'seller_accepted',
                    'seller_notes' => $item['seller_notes'] ?? null,
                    'seller_confirmed_at' => now()
                ]);

                event(new ProductStockUpdated($product->fresh()));
            }

            // Update order status
            $order->update(['status' => 'awaiting_buyer_confirmation']);

            DB::commit();

            return response()->json([
                'message' => 'Order verification completed successfully',
                'order_id' => $orderId,
                'status' => 'awaiting_buyer_confirmation'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel order before confirmation (returns reserved stock)
     */
    public function cancelOrder(Request $request, $orderId)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            // Check if user is authorized (buyer or seller)
            if ($order->user_id != $request->user_id && $order->seller_id != $request->user_id) {
                throw new \Exception('Unauthorized: You can only cancel your own orders');
            }

            if (!in_array($order->status, ['for_seller_verification', 'awaiting_buyer_confirmation'])) {
                throw new \Exception('Order cannot be cancelled at this stage');
            }

            foreach ($order->items as $orderItem) {
                if ($orderItem->reserved) {
                    $product = $orderItem->product;
                    $product->releaseStock($orderItem->estimated_weight_kg);
                    event(new ProductStockUpdated($product->fresh()));

                    $orderItem->update([
                        'reserved' => false,
                        'seller_verification_status' => 'cancelled'
                    ]);
                }
            }

            $order->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order_id' => $orderId,
                'status' => 'cancelled'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Buyer confirms final order with actual weights and pays
     */
    public function buyerConfirm(Request $request, $orderId)
    {
        $request->validate([
            'buyer_id' => 'required|integer',
            'confirm' => 'required|boolean',
            'payment_method' => 'required|string|in:cod,online,wallet'
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            if ($order->user_id != $request->buyer_id) {
                throw new \Exception('Unauthorized: You can only confirm your own orders');
            }

            if ($order->status !== 'awaiting_buyer_confirmation') {
                throw new \Exception('Order is not awaiting buyer confirmation');
            }

            if (!$request->confirm) {
                // Buyer rejects - cancel order
                return $this->cancelOrder($request, $orderId);
            }

            // Calculate final total based on actual weights
            $finalTotal = 0;
            foreach ($order->items as $orderItem) {
                if ($orderItem->isSellerAccepted()) {
                    $finalTotal += $orderItem->getFinalPrice();
                }
            }

            // Update order
            $order->update([
                'status' => 'confirmed',
                'total_price' => $finalTotal,
                'payment_method' => $request->payment_method
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order confirmed successfully',
                'order_id' => $orderId,
                'status' => 'confirmed',
                'final_total' => $finalTotal
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}