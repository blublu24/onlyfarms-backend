<?php

namespace App\Http\Controllers;

use App\Models\Preorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreorderController extends Controller
{
    /**
     * Create a preorder
     */
    public function store(Request $request)
    {
        $request->validate([
            'consumer_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,product_id',
            'seller_id'   => 'required|exists:users,id',
            'quantity'    => 'required|numeric|min:0.1',
            'expected_availability_date' => 'required|date',
        ]);

        $preorder = Preorder::create($request->all());

        return response()->json([
            'message'  => 'Preorder created successfully',
            'preorder' => $preorder,
        ], 201);
    }

    /**
     * List all preorders with related product, consumer, and seller
     */
    public function index()
    {
        $preorders = Preorder::with(['product', 'consumer', 'seller'])->get();

        return response()->json($preorders);
    }

    /**
     * Get a single preorder by ID
     */
    public function show($id)
    {
        $preorder = Preorder::with(['product', 'consumer', 'seller'])->findOrFail($id);

        return response()->json($preorder);
    }

    /**
     * Update a preorder
     */
    public function update(Request $request, $id)
    {
        $preorder = Preorder::findOrFail($id);

        $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'expected_availability_date' => 'sometimes|date',
            'status' => 'sometimes|string|in:pending,confirmed,ready,completed,cancelled',
        ]);

        $preorder->update($request->only([
            'quantity',
            'expected_availability_date',
            'status',
            'notes'
        ]));

        return response()->json([
            'message' => 'Preorder updated successfully',
            'preorder' => $preorder->fresh(['product', 'consumer', 'seller']),
        ]);
    }

    /**
     * Cancel a preorder
     */
    public function cancel($id)
    {
        $preorder = Preorder::findOrFail($id);

        // Check if already cancelled
        if ($preorder->status === 'cancelled') {
            return response()->json([
                'message' => 'Preorder is already cancelled'
            ], 400);
        }

        // Check if already completed
        if ($preorder->status === 'completed') {
            return response()->json([
                'message' => 'Cannot cancel a completed preorder'
            ], 400);
        }

        $preorder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Preorder cancelled successfully',
            'preorder' => $preorder->fresh(['product', 'consumer', 'seller']),
        ]);
    }

    /**
     * Accept a preorder (seller action) - CONVERTS TO ORDER
     */
    public function accept($id)
    {
        $preorder = Preorder::findOrFail($id);

        // Check if already accepted, rejected, or cancelled
        if (in_array($preorder->status, ['accepted', 'rejected', 'cancelled', 'completed'])) {
            return response()->json([
                'message' => 'Preorder cannot be accepted in its current status'
            ], 400);
        }

        // Convert preorder to order (following the correct flow)
        $order = \App\Models\Order::create([
            'user_id' => $preorder->consumer_id,
            'seller_id' => $preorder->seller_id,
            'total' => ($preorder->unit_price ?? 0) * ($preorder->unit_weight_kg ?? $preorder->quantity),
            'status' => 'pending',
            'payment_method' => 'cod',
            'delivery_address' => 'Preorder - Address to be confirmed',
            'delivery_method' => 'delivery',
            'note' => 'Preorder accepted and converted to order (Preorder #' . $preorder->id . ')',
            'preorder_id' => $preorder->id,
        ]);

        // Create order item
        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $preorder->product_id,
            'seller_id' => $preorder->seller_id,
            'product_name' => $preorder->product->product_name ?? 'Unknown Product',
            'price' => ($preorder->unit_price ?? 0) * ($preorder->unit_weight_kg ?? $preorder->quantity),
            'quantity' => $preorder->quantity,
            'unit' => $preorder->unit_key ?? 'kg',
            'estimated_weight_kg' => $preorder->unit_weight_kg ?? 0,
            'price_per_kg_at_order' => $preorder->unit_price ?? 0,
            'variation_type' => $preorder->variation_type,
            'variation_name' => $preorder->variation_name,
        ]);

        // Update preorder status
        $preorder->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Preorder accepted and converted to order successfully',
            'order' => $order->fresh(['items']),
            'preorder' => $preorder->fresh(['product', 'consumer', 'seller']),
        ]);
    }

    /**
     * Reject a preorder (seller action)
     */
    public function reject($id)
    {
        $preorder = Preorder::findOrFail($id);

        // Check if already rejected or cancelled
        if (in_array($preorder->status, ['rejected', 'cancelled', 'completed'])) {
            return response()->json([
                'message' => 'Preorder cannot be rejected in its current status'
            ], 400);
        }

        $preorder->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return response()->json([
            'message' => 'Preorder rejected successfully',
            'preorder' => $preorder->fresh(['product', 'consumer', 'seller']),
        ]);
    }

    /**
     * Get all preorders for a consumer (buyer)
     */
    public function consumerPreorders(Request $request)
    {
        $userId = auth()->id();

        $preorders = Preorder::with(['product', 'seller'])
            ->where('consumer_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($preorders);
    }

    /**
     * Get all preorders for a seller
     */
    public function sellerPreorders(Request $request)
    {
        $userId = auth()->id();

        $preorders = Preorder::with(['product', 'consumer'])
            ->where('seller_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($preorders);
    }

    /**
     * Check preorder eligibility for a product
     * Public endpoint: GET /products/{id}/preorder-eligibility
     * Returns a simple, forward-compatible payload used by the mobile app.
     */
    public function checkEligibility($id)
    {
        try {
            // Find product by its public key column `product_id`
            $product = DB::table('products')->where('product_id', $id)->first();

            if (!$product) {
                return response()->json([
                    'eligible' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Basic rule: if stocks are low (<=2kg) OR an upcoming crop schedule exists, allow preorder
            $stockKg = (float)($product->stock_kg ?? 0);

            // Try to find the nearest upcoming crop schedule for this product
            $nextSchedule = DB::table('crop_schedules')
                ->where('product_id', $product->product_id)
                ->where('is_active', true)
                ->whereNotNull('expected_harvest_start')
                ->where('expected_harvest_start', '>=', now()->toDateString()) // Include TODAY and future harvest dates
                ->orderBy('expected_harvest_start', 'asc')
                ->first();

            $eligible = ($stockKg <= 2) || !is_null($nextSchedule);

            // Build variations array with available stock information
            $variations = [];
            
            // Check premium variation
            $premiumStock = (float)($product->premium_stock_kg ?? 0);
            if ($premiumStock > 0) {
                $variations[] = [
                    'type' => 'premium',
                    'name' => 'Premium',
                    'available_kg' => $premiumStock,
                    'price_per_kg' => (float)($product->premium_price_per_kg ?? $product->price_per_kg ?? 0),
                ];
            }
            
            // Check Type A variation
            $typeAStock = (float)($product->type_a_stock_kg ?? 0);
            if ($typeAStock > 0) {
                $variations[] = [
                    'type' => 'type_a',
                    'name' => 'Type A',
                    'available_kg' => $typeAStock,
                    'price_per_kg' => (float)($product->type_a_price_per_kg ?? $product->price_per_kg ?? 0),
                ];
            }
            
            // Check Type B variation
            $typeBStock = (float)($product->type_b_stock_kg ?? 0);
            if ($typeBStock > 0) {
                $variations[] = [
                    'type' => 'type_b',
                    'name' => 'Type B',
                    'available_kg' => $typeBStock,
                    'price_per_kg' => (float)($product->type_b_price_per_kg ?? $product->price_per_kg ?? 0),
                ];
            }
            
            // If no variation-specific stocks, use standard
            if (empty($variations) && $stockKg > 0) {
                $variations[] = [
                    'type' => 'standard',
                    'name' => 'Standard',
                    'available_kg' => $stockKg,
                    'price_per_kg' => (float)($product->price_per_kg ?? 0),
                ];
            }

            return response()->json([
                'eligible' => (bool)$eligible,
                'harvest_date' => $nextSchedule->expected_harvest_start ?? null,
                'variations' => $variations,
                'estimated_yield' => $nextSchedule ? [
                    'quantity' => $nextSchedule->quantity_estimate ?? 0,
                    'unit' => $nextSchedule->quantity_unit ?? 'kg',
                    'harvest_start' => $nextSchedule->expected_harvest_start,
                    'harvest_end' => $nextSchedule->expected_harvest_end,
                ] : null,
            ]);
        } catch (\Throwable $e) {
            // Never break the app; return a safe default with context
            return response()->json([
                'eligible' => false,
                'harvest_date' => null,
                'variations' => [],
                'error' => 'ELIGIBILITY_CHECK_FAILED',
                'message' => $e->getMessage(),
            ], 200);
        }
    }
}
