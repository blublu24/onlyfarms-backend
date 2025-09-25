<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Address;
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

        return response()->json($orders);
    }

    /**
     * Buyer: View a single order
     */
    public function show(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load('items'));
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
                'items.*.unit' => 'required|string|in:kg,sack,piece',
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
                $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

                $total = 0.0;
                $lineItems = [];

                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (!$product) abort(422, "Product {$row['product_id']} not found");

                    $qty = (int) $row['quantity'];
                    $unit = $row['unit'];
                    $unitPrice = (float) $product->price;
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
                        'image_url' => $fullImage,
                    ];
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
                                    'line_items' => [[
                                        'currency' => 'PHP',
                                        'amount' => $amount,
                                        'name' => 'Order #' . $order->id,
                                        'quantity' => 1,
                                    ]],
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
        if (!$seller) return response()->json(['message' => 'You are not a seller'], 403);

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


    /**
     * Seller: Update order status manually
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        \Log::info('Raw PayMongo payload:', $payload);

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
                    $order->status = 'completed';
                    $order->payment_status = 'paid';
                    $order->save();

                    \Log::info("Order {$orderId} updated to paid/completed");
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


    public function markCODDelivered($id)
    {
        // Step 1: Find the order in the database
        $order = Order::find($id);

        // Step 2: Check if order exists
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Step 3: Make sure it’s COD
        if ($order->payment_method !== 'cod') {
            return response()->json(['message' => 'Not a COD order'], 400);
        }

        // Step 4: Update status
        $order->status = 'completed';
        $order->payment_status = 'paid';
        $order->save(); // save changes to database

        // Step 5: Send confirmation
        return response()->json([
            'message' => 'The order has been delivered.',
            'order' => $order
        ]);
    }

}