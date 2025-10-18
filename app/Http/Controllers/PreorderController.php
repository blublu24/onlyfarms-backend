<?php

namespace App\Http\Controllers;

use App\Models\Preorder;
use App\Models\Product;
use App\Models\CropSchedule;
use App\Models\UnitConversion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Address;
use App\Events\PreorderCreated;
use App\Events\PreorderFulfilled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreorderController extends Controller
{
    /**
     * Create a preorder with unit-specific data
     */
    public function store(Request $request)
    {
        $request->validate([
            'consumer_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,product_id',
            'seller_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'expected_availability_date' => 'required|date',
            
            // New unit-specific fields
            'variation_type' => 'required|string|in:premium,type_a,type_b,regular',
            'variation_name' => 'nullable|string|max:255',
            'unit_key' => 'required|string',
            'unit_price' => 'required|numeric|min:0',
            'unit_weight_kg' => 'required|numeric|min:0',
        ]);

        // Get product to validate unit availability
        $product = Product::findOrFail($request->product_id);
        
        // Validate unit is available for this product
        $validUnits = $product->getValidUnits();
        if (!in_array($request->unit_key, $validUnits)) {
            return response()->json([
                'message' => 'Invalid unit for this product',
                'valid_units' => $validUnits
            ], 400);
        }

        // Validate variation exists and has stock
        $variationStock = $this->getVariationStock($product, $request->variation_type);
        if ($variationStock <= 2) {
            // Check if crop schedule exists for preorder eligibility
            $hasCropSchedule = $product->cropSchedules()
                ->where('is_active', true)
                ->whereNotNull('expected_harvest_start')
                ->exists();
                
            if (!$hasCropSchedule) {
                return response()->json([
                    'message' => 'Product is not eligible for preorders - no active crop schedule'
                ], 400);
            }
        }

        // Create preorder with unit data
        $preorderData = $request->only([
            'consumer_id', 'product_id', 'seller_id', 'quantity', 
            'expected_availability_date', 'variation_type', 'variation_name',
            'unit_key', 'unit_price', 'unit_weight_kg'
        ]);
        
        $preorderData['status'] = 'pending';
        $preorderData['reserved_qty'] = $request->quantity * $request->unit_weight_kg;

        $preorder = Preorder::create($preorderData);

        // Dispatch event for real-time notifications
        event(new PreorderCreated($preorder));

        return response()->json([
            'message' => 'Preorder created successfully',
            'preorder' => $preorder->load(['product', 'consumer', 'seller']),
        ], 201);
    }

    /**
     * List all preorders with related product, consumer, and seller
     */
    public function index()
    {
        $preorders = Preorder::with(['product', 'consumer', 'seller'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $preorders,
            'message' => 'Preorders retrieved successfully'
        ]);
    }

    /**
     * Check preorder eligibility for a product
     */
    public function checkEligibility($productId)
    {
        $product = Product::with(['cropSchedules'])->findOrFail($productId);
        
        // Check if product has active crop schedule
        $hasCropSchedule = $product->cropSchedules()
            ->where('is_active', true)
            ->whereNotNull('expected_harvest_start')
            ->exists();
            
        if (!$hasCropSchedule) {
            return response()->json([
                'eligible' => false,
                'message' => 'No active crop schedule found'
            ]);
        }

        // Get variations and their stock levels
        $variations = $this->getProductVariations($product);
        
        // Check if any variation has stock <= 2kg
        $eligibleVariations = collect($variations)->filter(function ($variation) {
            return $variation['stock_kg'] <= 2;
        })->values();

        $eligible = $eligibleVariations->isNotEmpty();

        return response()->json([
            'eligible' => $eligible,
            'harvest_date' => $product->getExpectedAvailabilityDate(),
            'variations' => $variations,
            'message' => $eligible ? 'Product is eligible for preorders' : 'Product has sufficient stock'
        ]);
    }

    /**
     * Get individual preorder details
     */
    public function show(Request $request, $id)
    {
        $preorder = Preorder::with(['product', 'consumer', 'seller'])
            ->findOrFail($id);

        // Check if user has access to this preorder (seller or consumer)
        $user = $request->user();
        if ($preorder->seller_id !== $user->id && $preorder->consumer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized access to preorder'
            ], 403);
        }

        return response()->json([
            'data' => $preorder,
            'message' => 'Preorder details retrieved successfully'
        ]);
    }

    /**
     * Update preorder (harvest date, notes, etc.)
     */
    public function update(Request $request, $id)
    {
        $preorder = Preorder::findOrFail($id);

        // Check if user is the seller
        if ($preorder->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the seller can update this preorder'
            ], 403);
        }

        $request->validate([
            'harvest_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        $preorder->update($request->only(['harvest_date', 'notes']));

        return response()->json([
            'data' => $preorder->fresh(['product', 'consumer', 'seller']),
            'message' => 'Preorder updated successfully'
        ]);
    }

    /**
     * Get seller's preorders
     */
    public function sellerPreorders(Request $request)
    {
        $sellerId = $request->user()->id;
        
        $preorders = Preorder::with(['product', 'consumer'])
            ->where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $preorders,
            'message' => 'Seller preorders retrieved successfully'
        ]);
    }

    /**
     * Get consumer's preorders
     */
    public function consumerPreorders(Request $request)
    {
        $consumerId = $request->user()->id;
        
        $preorders = Preorder::with(['product', 'seller'])
            ->where('consumer_id', $consumerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $preorders,
            'message' => 'Consumer preorders retrieved successfully'
        ]);
    }

    /**
     * Fulfill a preorder (convert to order)
     */
    public function fulfill(Request $request, $preorderId)
    {
        $preorder = Preorder::findOrFail($preorderId);
        
        // Validate seller owns this preorder
        if ($preorder->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$preorder->canBeFulfilled()) {
            return response()->json([
                'message' => 'Preorder cannot be fulfilled at this time'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Update preorder status
            $preorder->updateStatus('fulfilled');

            // Create order from preorder
            $order = $this->createOrderFromPreorder($preorder);

            // Dispatch event for real-time notifications
            event(new PreorderFulfilled($preorder, $order));

            DB::commit();

            return response()->json([
                'message' => 'Preorder fulfilled successfully',
                'order' => $order,
                'preorder' => $preorder
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to fulfill preorder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a preorder
     */
    public function cancel(Request $request, $preorderId)
    {
        $preorder = Preorder::findOrFail($preorderId);
        
        // Validate user can cancel this preorder
        $userId = $request->user()->id;
        if ($preorder->consumer_id !== $userId && $preorder->seller_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$preorder->canBeCancelled()) {
            return response()->json([
                'message' => 'Preorder cannot be cancelled at this time'
            ], 400);
        }

        $preorder->updateStatus('cancelled');

        return response()->json([
            'message' => 'Preorder cancelled successfully',
            'preorder' => $preorder
        ]);
    }

    /* ==============================
     | Helper Methods
     ============================== */

    /**
     * Get variation stock for a product
     */
    private function getVariationStock(Product $product, string $variationType): float
    {
        switch ($variationType) {
            case 'premium':
                return $product->premium_stock_kg ?? 0;
            case 'type_a':
                return $product->type_a_stock_kg ?? 0;
            case 'type_b':
                return $product->type_b_stock_kg ?? 0;
            case 'regular':
            default:
                return $product->stock_kg ?? 0;
        }
    }

    /**
     * Get product variations with unit options
     */
    private function getProductVariations(Product $product): array
    {
        $variations = [];
        $validUnits = $product->getValidUnits();
        
        // Regular variation
        $variations[] = [
            'id' => 'regular',
            'name' => 'Regular',
            'type' => 'regular',
            'stock_kg' => $product->stock_kg,
            'price_per_kg' => $product->price_per_kg,
            'units' => $this->getUnitOptions($product, $validUnits, 'regular')
        ];

        // Premium variation
        if ($product->premium_stock_kg > 0) {
            $variations[] = [
                'id' => 'premium',
                'name' => 'Premium',
                'type' => 'premium',
                'stock_kg' => $product->premium_stock_kg,
                'price_per_kg' => $product->premium_price_per_kg,
                'units' => $this->getUnitOptions($product, $validUnits, 'premium')
            ];
        }

        // Type A variation
        if ($product->type_a_stock_kg > 0) {
            $variations[] = [
                'id' => 'type_a',
                'name' => 'Type A',
                'type' => 'type_a',
                'stock_kg' => $product->type_a_stock_kg,
                'price_per_kg' => $product->type_a_price_per_kg,
                'units' => $this->getUnitOptions($product, $validUnits, 'type_a')
            ];
        }

        // Type B variation
        if ($product->type_b_stock_kg > 0) {
            $variations[] = [
                'id' => 'type_b',
                'name' => 'Type B',
                'type' => 'type_b',
                'stock_kg' => $product->type_b_stock_kg,
                'price_per_kg' => $product->type_b_price_per_kg,
                'units' => $this->getUnitOptions($product, $validUnits, 'type_b')
            ];
        }

        return $variations;
    }

    /**
     * Get unit options for a variation
     */
    private function getUnitOptions(Product $product, array $validUnits, string $variationType): array
    {
        $units = [];
        $basePrice = $this->getVariationPrice($product, $variationType);
        $vegetableSlug = $product->getVegetableSlug();
        
        // If no valid units from database, use all possible units
        if (empty($validUnits)) {
            $validUnits = ['kg', 'sack', 'small_sack', 'packet', 'tali', 'pieces'];
        }
        
        foreach ($validUnits as $unitKey) {
            $weight = UnitConversion::getStandardWeight($vegetableSlug, $unitKey);
            
            // If no weight from database, use vegetable-specific default weights
            if ($weight <= 0) {
                $weight = $this->getDefaultWeight($unitKey, $vegetableSlug);
            }
            
            // Skip units that are not available for this vegetable (weight = 0)
            if ($weight <= 0) {
                continue;
            }
            
            $price = $basePrice * $weight;
            
            $units[] = [
                'key' => $unitKey,
                'label' => ucfirst(str_replace('_', ' ', $unitKey)),
                'weight_kg' => $weight,
                'price' => round($price, 2)
            ];
        }
        
        return $units;
    }

    /**
     * Get default weight for a unit when database doesn't have data
     * Vegetable-specific weights based on real-world measurements
     */
    private function getDefaultWeight(string $unitKey, string $vegetableSlug): float
    {
        $vegetableWeights = [
            // Eggplant (talong)
            'talong' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0.3, // Eggplant tali is typically 0.3kg (3-4 pieces)
                'packet' => 0, // Not available for eggplant
                'pieces' => 0, // Not available for eggplant
            ],
            
            // Tomato (kamatis)
            'kamatis' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for tomatoes
                'packet' => 0, // Not available for tomatoes
                'pieces' => 0.2, // Tomato pieces are typically 0.2kg each
            ],
            
            // Onion (sibuyas)
            'sibuyas' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for onions
                'packet' => 0.5, // Onion packets are typically 0.5kg
                'pieces' => 0, // Not available for onions
            ],
            
            // Garlic (bawang)
            'bawang' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0.1, // Garlic tali is typically 0.1kg (small bundle)
                'packet' => 0.2, // Garlic packets are typically 0.2kg
                'pieces' => 0, // Not available for garlic
            ],
            
            // Squash (kalabasa)
            'kalabasa' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for squash
                'packet' => 0, // Not available for squash
                'pieces' => 1.5, // Squash pieces are typically 1.5kg each
            ],
            
            // Okra
            'okra' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0.2, // Okra tali is typically 0.2kg
                'packet' => 0, // Not available for okra
                'pieces' => 0, // Not available for okra
            ],
            
            // String beans (sitaw)
            'sitaw' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0.25, // Sitaw tali is typically 0.25kg
                'packet' => 0, // Not available for sitaw
                'pieces' => 0, // Not available for sitaw
            ],
            
            // Water spinach (kangkong)
            'kangkong' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0.15, // Kangkong tali is typically 0.15kg
                'packet' => 0, // Not available for kangkong
                'pieces' => 0, // Not available for kangkong
            ],
            
            // Pechay
            'pechay' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0.2, // Pechay tali is typically 0.2kg
                'packet' => 0, // Not available for pechay
                'pieces' => 0, // Not available for pechay
            ],
            
            // Cabbage (repolyo)
            'repolyo' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for cabbage
                'packet' => 0, // Not available for cabbage
                'pieces' => 2.0, // Cabbage pieces are typically 2kg each
            ],
            
            // Carrot
            'carrots' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for carrots
                'packet' => 0.5, // Carrot packets are typically 0.5kg
                'pieces' => 0, // Not available for carrots
            ],
            
            // Sayote
            'sayote' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for sayote
                'packet' => 0, // Not available for sayote
                'pieces' => 0.8, // Sayote pieces are typically 0.8kg each
            ],
            
            // Potato (patatas)
            'patatas' => [
                'kg' => 1.0,
                'sack' => 15.0,
                'small_sack' => 10.0,
                'tali' => 0, // Not available for potatoes
                'packet' => 0, // Not available for potatoes
                'pieces' => 0.3, // Potato pieces are typically 0.3kg each
            ],
        ];
        
        // Get weights for the specific vegetable
        $weights = $vegetableWeights[$vegetableSlug] ?? $vegetableWeights['talong']; // Default to eggplant
        
        return $weights[$unitKey] ?? 1.0;
    }

    /**
     * Get price for a variation
     */
    private function getVariationPrice(Product $product, string $variationType): float
    {
        switch ($variationType) {
            case 'premium':
                return $product->premium_price_per_kg ?? $product->price_per_kg;
            case 'type_a':
                return $product->type_a_price_per_kg ?? $product->price_per_kg;
            case 'type_b':
                return $product->type_b_price_per_kg ?? $product->price_per_kg;
            default:
                return $product->price_per_kg;
        }
    }

    /**
     * Create order from preorder
     */
    private function createOrderFromPreorder(Preorder $preorder)
    {
        // Create the main order
        $order = Order::create([
            'user_id' => $preorder->consumer_id,
            'address_id' => null, // Will need to get from consumer's default address
            'total' => $preorder->getSubtotalAttribute(),
            'status' => 'pending',
            'delivery_address' => $this->getConsumerDeliveryAddress($preorder->consumer_id),
            'note' => $preorder->notes ?? 'Order created from preorder #' . $preorder->id,
            'payment_method' => 'cod', // Default to COD for preorders
            'payment_status' => 'pending',
            'preorder_id' => $preorder->id,
        ]);

        // Create order item from preorder data
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $preorder->product_id,
            'seller_id' => $preorder->seller_id,
            'product_name' => $preorder->product->product_name ?? 'Unknown Product',
            'price' => $preorder->unit_price ?? $preorder->product->price_per_kg ?? 0,
            'quantity' => $preorder->quantity,
            'unit' => $preorder->unit_key ?? 'kg',
            'image_url' => $preorder->product->image_url ?? null,
            
            // Variation data
            'variation_type' => $preorder->variation_type,
            'variation_name' => $preorder->variation_name,
            
            // Weight and pricing data
            'estimated_weight_kg' => $preorder->getTotalWeightKgAttribute(),
            'actual_weight_kg' => null, // To be filled by seller verification
            'price_per_kg_at_order' => $preorder->product->price_per_kg ?? 0,
            'estimated_price' => $preorder->getSubtotalAttribute(),
            'reserved' => true, // Mark as reserved since it came from preorder
            'seller_verification_status' => 'pending',
            'seller_notes' => null,
            'seller_confirmed_at' => null,
        ]);

        // Update product stock (reduce by reserved quantity)
        $this->updateProductStock($preorder);

        return $order->load(['items', 'user']);
    }

    /**
     * Get consumer's delivery address
     */
    private function getConsumerDeliveryAddress($consumerId)
    {
        $consumer = User::find($consumerId);
        if (!$consumer) {
            return 'Address not available';
        }

        // Try to get default address
        $defaultAddress = Address::where('user_id', $consumerId)
            ->where('is_default', true)
            ->first();

        if ($defaultAddress) {
            return $defaultAddress->address;
        }

        // Fallback to any address
        $anyAddress = Address::where('user_id', $consumerId)->first();
        if ($anyAddress) {
            return $anyAddress->address;
        }

        return 'Address not available';
    }

    /**
     * Update product stock after preorder fulfillment
     */
    private function updateProductStock(Preorder $preorder)
    {
        $product = $preorder->product;
        if (!$product) return;

        $reservedWeight = $preorder->getTotalWeightKgAttribute();

        // Update stock based on variation type
        switch ($preorder->variation_type) {
            case 'premium':
                $product->decrement('premium_stock_kg', $reservedWeight);
                break;
            case 'type_a':
                $product->decrement('type_a_stock_kg', $reservedWeight);
                break;
            case 'type_b':
                $product->decrement('type_b_stock_kg', $reservedWeight);
                break;
            case 'regular':
            default:
                $product->decrement('stock_kg', $reservedWeight);
                break;
        }
    }
}
