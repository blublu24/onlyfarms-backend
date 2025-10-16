<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Address;
use App\Models\OrderItem;
use App\Models\UnitConversion;
use App\Events\ProductStockUpdated;
use App\Events\ProductSalesUpdated;
use App\Events\NewOrderNotification;
use App\Events\OrderDeliveredNotification;
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

        $orderWithRelations = $order->load(['items.seller', 'user', 'address']);
        
        // Debug: Log seller information
        foreach ($orderWithRelations->items as $item) {
            Log::info('Order item seller debug:', [
                'item_id' => $item->id,
                'seller_id' => $item->seller_id,
                'seller_loaded' => $item->seller ? 'yes' : 'no',
                'seller_name' => $item->seller->shop_name ?? 'null',
            ]);
        }
        
        return response()->json([
            'message' => 'Order fetched successfully',
            'data' => $orderWithRelations
        ]);
    }

    /**
     * Buyer: Place a new order
     */
    public function store(Request $request)
    {
        try {
            Log::info('Order creation started', ['user_id' => $request->user()->id]);
            Log::info('Raw request data:', $request->all());
            Log::info('🚀 BACKEND CHANGES ARE ACTIVE - Order creation with new logic!');
            
            // Debug: Log each item being processed
            foreach ($request->input('items', []) as $index => $item) {
                Log::info("Processing item {$index}:", [
                    'product_id' => $item['product_id'] ?? 'missing',
                    'quantity' => $item['quantity'] ?? 'missing',
                    'unit' => $item['unit'] ?? 'missing',
                    'variation_type' => $item['variation_type'] ?? 'missing',
                    'variation_name' => $item['variation_name'] ?? 'missing',
                    'variation_price' => $item['variation_price'] ?? 'missing',
                ]);
            }

            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,product_id',
                'items.*.quantity' => 'required|integer|min:1|max:999',
                'items.*.unit' => 'required|string|in:kg,sack,small_sack,packet,tali,piece,bunches',
                'items.*.variation_type' => 'nullable|string|in:premium,typeA,typeB,typeC',
                'items.*.variation_name' => 'nullable|string',
                'items.*.variation_price' => 'nullable|numeric|min:0',
                'address_id' => 'required|exists:addresses,address_id',
                'notes' => 'nullable|string|max:500',
                'payment_method' => 'nullable|string|in:cod,gcash,card',
            ]);

            Log::info('Validated data:', $data);

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
                    $variationPricePerKg = null;
                    
                    if ($unit === 'kg') {
                        // For kg units, if we have variation_price, calculate weight from total price
                        if (isset($row['variation_price']) && $row['variation_price']) {
                            // First determine the price per kg for this variation
                            $variationPricePerKg = $product->price_per_kg; // Default to base price
                            
                            // If it's a variation, use the variation-specific price
                            if (isset($row['variation_type'])) {
                                switch ($row['variation_type']) {
                                    case 'premium':
                                        $variationPricePerKg = $product->premium_price_per_kg ?? $product->price_per_kg;
                                        break;
                                    case 'typeA':
                                        $variationPricePerKg = $product->type_a_price_per_kg ?? $product->price_per_kg;
                                        break;
                                    case 'typeB':
                                        $variationPricePerKg = $product->type_b_price_per_kg ?? $product->price_per_kg;
                                        break;
                                }
                            }
                            
                            // Calculate weight from total price and variation price per kg
                            if ($variationPricePerKg > 0) {
                                $estimatedWeightKg = $row['variation_price'] / $variationPricePerKg;
                            } else {
                                $estimatedWeightKg = $qty; // Fallback to quantity
                            }
                        } else {
                            $estimatedWeightKg = $qty; // Direct kg quantity
                        }
                    } else {
                        // Get vegetable slug and calculate weight from unit conversion
                        $vegetableSlug = $product->getVegetableSlug();
                        $standardWeightPerUnit = \App\Models\UnitConversion::getStandardWeight($vegetableSlug, $unit);
                        
                        // If unit conversion returns 0, use fallback weights
                        if ($standardWeightPerUnit <= 0) {
                            // Fallback weights for common units
                            $fallbackWeights = [
                                'packet' => 0.25,  // 250g per packet
                                'tali' => 0.3,     // 300g per tali
                                'piece' => 0.2,    // 200g per piece
                                'sack' => 15,      // 15kg per sack
                                'small_sack' => 10, // 10kg per small sack
                            ];
                            $standardWeightPerUnit = $fallbackWeights[$unit] ?? 0.1; // Default 100g
                        }
                        
                        $estimatedWeightKg = $qty * $standardWeightPerUnit;
                    }
                    
                    // Debug: Log weight calculation
                    Log::info('Weight calculation:', [
                        'product_name' => $product->product_name,
                        'unit' => $unit,
                        'quantity' => $qty,
                        'estimated_weight_kg' => $estimatedWeightKg,
                        'variation_type' => $row['variation_type'] ?? 'none',
                        'variation_price' => $row['variation_price'] ?? 'none',
                        'variation_price_per_kg' => $variationPricePerKg ?? 'N/A',
                        'vegetable_slug' => $vegetableSlug ?? 'N/A',
                        'standard_weight_per_unit' => $standardWeightPerUnit ?? 'N/A',
                    ]);
                    
                    // Determine price based on unit - use variation price if provided, otherwise use product price
                    $pricePerKg = $product->price_per_kg; // Default to product's base price per kg
                    $unitPrice = 0;
                    
                    // If variation_price is provided, it's the total price for the quantity
                    if (isset($row['variation_price']) && $row['variation_price']) {
                        $unitPrice = (float) $row['variation_price']; // This is the total price
                        // Calculate the actual price per kg from the total price
                        $pricePerKg = $estimatedWeightKg > 0 ? $unitPrice / $estimatedWeightKg : $product->price_per_kg;
                    } else if ($pricePerKg && $product->available_units) {
                        $availableUnits = is_string($product->available_units) 
                            ? json_decode($product->available_units, true) 
                            : $product->available_units;
                        
                        if ($unit === 'kg') {
                            $unitPrice = (float) $pricePerKg;
                        } else {
                            // For other units, calculate price per unit based on estimated weight
                            // unitPrice should be the price per unit (e.g., price per sack, price per bunch)
                            $unitPrice = (float) $pricePerKg * $estimatedWeightKg / $qty;
                        }
                    } else {
                        // Fallback to old pricing system
                        if ($unit === 'kg' && $product->price_kg) {
                            $unitPrice = (float) $product->price_kg;
                        } elseif ($unit === 'bunches' && $product->price_bunches) {
                            $unitPrice = (float) $product->price_bunches;
                        } else {
                            // If no price is found, use a default or throw an error
                            Log::error('No price found for product', [
                                'product_id' => $product->product_id,
                                'product_name' => $product->product_name,
                                'unit' => $unit,
                                'price_per_kg' => $product->price_per_kg,
                                'price_kg' => $product->price_kg ?? 'null',
                                'price_bunches' => $product->price_bunches ?? 'null'
                            ]);
                            $unitPrice = 0; // This will cause the order to fail validation
                        }
                    }
                    
                    // Calculate line total correctly
                    if (isset($row['variation_price']) && $row['variation_price']) {
                        // For variations, unitPrice is already the total price
                        $lineTotal = round($unitPrice, 2);
                    } else {
                        // For regular items, multiply unit price by quantity
                        $lineTotal = round($unitPrice * $qty, 2);
                    }
                    // Validate that we have a valid price
                    if ($unitPrice <= 0) {
                        abort(422, "Invalid price for product '{$product->product_name}'. Please contact support.");
                    }
                    
                    $total = round($total + $lineTotal, 2);

                    $raw = $product->image_url;
                    $fullImage = $raw && Str::startsWith($raw, ['http://', 'https://'])
                        ? $raw
                        : ($raw ? asset('storage/' . $raw) : null);

                    // Find the actual Seller ID (not User ID) for this product
                    $seller = \App\Models\Seller::where('user_id', $product->seller_id)->first();
                    $actualSellerId = $seller ? $seller->id : null;
                    
                    // Debug: Log seller lookup
                    Log::info('Seller lookup:', [
                        'product_name' => $product->product_name,
                        'product_seller_id' => $product->seller_id, // This is User ID
                        'found_seller' => $seller ? 'yes' : 'no',
                        'actual_seller_id' => $actualSellerId, // This should be Seller ID
                        'seller_name' => $seller ? $seller->shop_name : 'null',
                    ]);
                    
                    $lineItems[] = [
                        'product_id' => $product->product_id,
                        'seller_id' => $actualSellerId,
                        'product_name' => $product->product_name,
                        'price' => $unitPrice,
                        'quantity' => $qty,
                        'unit' => $unit,
                        'estimated_weight_kg' => round($estimatedWeightKg, 4),
                        'price_per_kg_at_order' => (float) $pricePerKg ?? 0,
                        'estimated_price' => $lineTotal,
                        'image_url' => $fullImage,
                        'variation_type' => $row['variation_type'] ?? null,
                        'variation_name' => $row['variation_name'] ?? null,
                    ];

                    // Debug logging for line item creation
                    Log::info('Line item created', [
                        'product_name' => $product->product_name,
                        'unit' => $unit,
                        'quantity' => $qty,
                        'estimated_weight_kg' => round($estimatedWeightKg, 4),
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'estimated_price' => $lineTotal,
                        'price_per_kg_at_order' => (float) $pricePerKg ?? 0,
                        'variation_type' => $row['variation_type'] ?? null,
                        'variation_name' => $row['variation_name'] ?? null,
                        'variation_price' => $row['variation_price'] ?? null,
                        'is_variation' => isset($row['variation_price']) && $row['variation_price'],
                        'seller_id' => $product->seller_id,
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
                    // Debug: Log what's being saved to database
                    Log::info('Creating order item in database:', [
                        'order_id' => $order->id,
                        'estimated_weight_kg' => $li['estimated_weight_kg'],
                        'price_per_kg_at_order' => $li['price_per_kg_at_order'],
                        'estimated_price' => $li['estimated_price'],
                        'variation_name' => $li['variation_name'] ?? 'none',
                    ]);
                    
                    $createdItem = $order->items()->create($li);
                    
                    // Debug: Log what was actually saved
                    Log::info('Order item created in database:', [
                        'item_id' => $createdItem->id,
                        'estimated_weight_kg' => $createdItem->estimated_weight_kg,
                        'price_per_kg_at_order' => $createdItem->price_per_kg_at_order,
                        'estimated_price' => $createdItem->estimated_price,
                    ]);
                }

                // ✅ STOCK MANAGEMENT: Don't decrease stock when order is placed
                // Stock will only be decreased when seller confirms the order
                Log::info('Order created without stock reduction - waiting for seller confirmation', [
                    'order_id' => $order->id,
                    'status' => 'pending',
                    'total_items' => count($lineItems)
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

        // 🔔 NOTIFICATION: Send delivery notification to buyer
        event(new OrderDeliveredNotification($order->fresh(), $order->user_id));

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
            'items.*.variation_type' => 'nullable|string|in:premium,typeA,typeB,typeC',
            'items.*.variation_name' => 'nullable|string',
            'items.*.variation_price' => 'nullable|numeric|min:0',
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

                // Calculate estimated price - use variation price if provided, otherwise use product price
                $pricePerKg = $item['variation_price'] ?? $product->price_per_kg;
                $estimatedPrice = $estimatedWeight * $pricePerKg;

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
                    'variation_type' => $item['variation_type'] ?? null,
                    'variation_name' => $item['variation_name'] ?? null,
                    'estimated_weight_kg' => $estimatedWeight,
                    'price_per_kg_at_order' => $pricePerKg,
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
     * Seller confirms order and decreases stock
     */
    public function sellerConfirmOrder(Request $request, $orderId)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            if (!$user->is_seller) {
                throw new \Exception('Unauthorized: You are not a seller');
            }
            
            // Get the actual Seller ID (not User ID) for this user
            $seller = $user->seller;
            if (!$seller) {
                throw new \Exception('Seller profile not found');
            }
            
            $order = Order::findOrFail($orderId);
            
            // Check if seller is authorized
            $hasSellerItems = $order->items()->where('seller_id', $seller->id)->exists();
            if (!$hasSellerItems) {
                throw new \Exception('Unauthorized: You can only confirm orders containing your products');
            }

            if ($order->status !== 'pending') {
                throw new \Exception('Order cannot be confirmed at this stage');
            }

            // Decrease stock for each item in this seller's products
            foreach ($order->items()->where('seller_id', $seller->id)->get() as $orderItem) {
                $product = Product::find($orderItem->product_id);
                if ($product) {
                    // Use actual_weight_kg (adjusted by seller) or fallback to estimated_weight_kg
                    $actualWeightKg = $orderItem->actual_weight_kg ?? $orderItem->estimated_weight_kg ?? 0;
                    
                    // Check if stock is sufficient
                    $currentStock = (float) $product->stock_kg;
                    if ($currentStock < $actualWeightKg) {
                        throw new \Exception("Insufficient stock for {$product->product_name}. Available: {$currentStock}kg, Required: {$actualWeightKg}kg");
                    }
                    
                    // Decrease stock atomically
                    $newStock = max(0, $currentStock - $actualWeightKg);
                    $product->update(['stock_kg' => $newStock]);
                    
                    // Update total sold with actual weight
                    $product->increment('total_sold', $actualWeightKg);
                    
                    // Broadcast stock update
                    event(new ProductStockUpdated($product->fresh()));
                    
                    Log::info('Stock decreased on seller confirmation', [
                        'order_id' => $order->id,
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'old_stock' => $currentStock,
                        'new_stock' => $newStock,
                        'decremented_by' => $actualWeightKg,
                        'actual_weight_kg' => $actualWeightKg,
                        'estimated_weight_kg' => $orderItem->estimated_weight_kg
                    ]);
                }
            }

            // Update order status to confirmed - waiting for delivery
            $order->update(['status' => 'confirmed_waiting_delivery']);

            DB::commit();

            return response()->json([
                'message' => 'Order confirmed successfully',
                'order_id' => $orderId,
                'status' => 'confirmed_waiting_delivery'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update order item (for weight adjustments)
     */
    public function updateOrderItem(Request $request, $orderId, $itemId)
    {
        $request->validate([
            'actual_weight_kg' => 'nullable|numeric|min:0',
            'seller_verification_status' => 'nullable|string|in:pending,seller_accepted,seller_rejected,seller_adjusted'
        ]);

        try {
            $orderItem = OrderItem::where('id', $itemId)
                ->where('order_id', $orderId)
                ->firstOrFail();

            // Check if user is authorized (seller of this item)
            $user = $request->user();
            if (!$user->is_seller) {
                throw new \Exception('Unauthorized: You are not a seller');
            }
            
            // Get the actual Seller ID (not User ID) for this user
            $seller = $user->seller;
            if (!$seller) {
                throw new \Exception('Seller profile not found');
            }
            
            if ($orderItem->seller_id != $seller->id) {
                throw new \Exception('Unauthorized: You can only update items from your own orders');
            }

            $updateData = [];
            if ($request->has('actual_weight_kg')) {
                $updateData['actual_weight_kg'] = $request->actual_weight_kg;
            }
            if ($request->has('seller_verification_status')) {
                $updateData['seller_verification_status'] = $request->seller_verification_status;
            }

            $orderItem->update($updateData);

            return response()->json([
                'message' => 'Order item updated successfully',
                'item' => $orderItem->fresh()
            ]);

        } catch (\Exception $e) {
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

            // Check if user is authorized (buyer only for now)
            if ($order->user_id != $request->user_id) {
                throw new \Exception('Unauthorized: You can only cancel your own orders');
            }

            if (!in_array($order->status, ['pending', 'for_seller_verification', 'awaiting_buyer_confirmation'])) {
                throw new \Exception('Order cannot be cancelled at this stage');
            }

            // Update order items status
            foreach ($order->items as $orderItem) {
                $orderItem->update([
                    'reserved' => false,
                    'seller_verification_status' => 'seller_rejected' // Use valid enum value
                ]);
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