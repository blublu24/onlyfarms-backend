<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Address;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            Log::info('Validation passed', ['address_id' => $data['address_id']]);

            // ✅ Ensure address belongs to this user
            $address = Address::where('address_id', $data['address_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            // ✅ Snapshot the address
            $deliveryAddress = $address->name . ', ' . $address->phone . ', ' . $address->address;

            return DB::transaction(function () use ($data, $user, $deliveryAddress) {
                $productIds = collect($data['items'])->pluck('product_id')->all();
                $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

                $total = 0.0;
                $lineItems = [];

                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (!$product) {
                        abort(422, "Product {$row['product_id']} not found");
                    }

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

                // ✅ Create order
                $orderData = [
                    'user_id' => $user->id,
                    'total' => $total,
                    'status' => 'pending',
                    'delivery_address' => $deliveryAddress,
                    'note' => $data['notes'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cod',
                    'payment_link' => null,           // Day 7 addition
                    'payment_status' => 'pending',    // Day 7 addition
                ];

                $order = Order::create($orderData);

                foreach ($lineItems as $li) {
                    $order->items()->create($li);
                }

               // ✅ Real PayMongo Checkout integration
if ($order->payment_method !== 'cod') {
    $client = new \GuzzleHttp\Client();

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
                        'amount' => (int) ($order->total * 100), // PayMongo expects cents
                        'name' => 'Order #' . $order->id,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => ['gcash', 'card'],
                    'success_url' => url('/payments/success/' . $order->id),
                    'cancel_url' => url('/payments/cancel/' . $order->id),
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
     * Seller: Get orders that contain their products
     */
    public function sellerOrders(Request $request)
    {
        $user = $request->user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json(['message' => 'You are not a seller'], 403);
        }

        $orders = Order::with(['items.product','user'])
            ->whereHas('items', function($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->orderBy('created_at','desc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Seller: Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);

        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        return response()->json($order);
    }
}
