<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load('items'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('Order creation started', ['user_id' => $request->user()->id]);

            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,product_id',
                'items.*.quantity' => 'required|integer|min:1|max:999',
                'items.*.unit' => 'required|string|in:kg,sack,piece', // ✅ Validate unit
                'address_id' => 'required|exists:addresses,address_id',
                'notes' => 'nullable|string|max:500',
                'payment_method' => 'nullable|string|in:cod,gcash,card',
            ]);

            $user = $request->user();
            Log::info('Validation passed', ['address_id' => $data['address_id']]);

            // Ensure address belongs to this user
            $address = Address::where('address_id', $data['address_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            // ✅ Snapshot the address
            $deliveryAddress = $address->name . ', ' . $address->phone . ', ' . $address->address;
            Log::info('Address found', ['delivery_address' => $deliveryAddress]);

            return DB::transaction(function () use ($data, $user, $deliveryAddress) {
                Log::info('Transaction started');

                $productIds = collect($data['items'])->pluck('product_id')->all();
                $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

                $total = 0.0;
                $lineItems = [];

                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (!$product) {
                        Log::error('Product not found', ['product_id' => $row['product_id']]);
                        abort(422, "Product {$row['product_id']} not found");
                    }

                    $qty = (int) $row['quantity'];
                    $unit = $row['unit']; // ✅ unit from request
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
                        'unit' => $unit, // ✅ save unit
                        'image_url' => $fullImage,
                    ];
                }

                Log::info('Line items calculated', ['total' => $total, 'items_count' => count($lineItems)]);

                // ✅ Create order
                $orderData = [
                    'user_id' => $user->id,
                    'total' => $total,
                    'status' => 'pending',
                    'delivery_address' => $deliveryAddress,
                    'note' => $data['notes'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cod',
                ];

                $order = Order::create($orderData);
                Log::info('Order created successfully', ['order_id' => $order->id]);

                foreach ($lineItems as $li) {
                    $order->items()->create($li);
                }

                Log::info('Order items created', ['items_count' => count($lineItems)]);

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => $order->load('items')
                ], 201);

            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
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
}
