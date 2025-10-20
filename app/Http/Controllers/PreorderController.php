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
        
        // Validate unit is available for this product using enhanced unit system
        $validUnits = $product->getValidUnits(); // can be array of strings (keys) or array of unit info
        $unitExists = false;
        foreach ($validUnits as $unit) {
            $key = is_array($unit) ? ($unit['key'] ?? ($unit['name'] ?? null)) : $unit;
            $enabled = is_array($unit) ? ($unit['enabled'] ?? true) : true;
            if ($key === $request->unit_key && $enabled) {
                $unitExists = true;
                break;
            }
        }
        
        if (!$unitExists) {
            return response()->json([
                'message' => 'Invalid or disabled unit for this product',
                'valid_units' => array_column($validUnits, 'key')
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

        // Calculate reserved quantity for this preorder
        $reservedQty = $request->quantity * $request->unit_weight_kg;

        // Check if product can accept this preorder (doesn't exceed expected harvest)
        if (!$product->canAcceptPreorder($reservedQty, $request->variation_type)) {
            return response()->json([
                'message' => 'Cannot accept preorder - reserved quantities exceed expected harvest',
                'current_reserved' => $product->getReservedQuantity($request->variation_type),
                'requested' => $reservedQty,
                'expected_harvest' => $product->getExpectedHarvestQuantity()
            ], 400);
        }

        // Get unit information from the enhanced unit system to ensure accuracy
        $vegetableSlug = $product->getVegetableSlug();
        $unitInfo = UnitConversion::getUnitInfo($vegetableSlug, $request->unit_key);
        if (!$unitInfo) {
            // Fallback to default mapping when DB has no entry for this vegetable/unit
            $fallbackWeight = $this->getDefaultWeight($request->unit_key, $vegetableSlug);
            if ($fallbackWeight > 0) {
                $unitInfo = [
                    'key' => $request->unit_key,
                    'label' => ucfirst(str_replace('_', ' ', $request->unit_key)),
                    'weight_kg' => $fallbackWeight,
                    'description' => null,
                    'enabled' => true,
                ];
            } else {
                return response()->json([
                    'message' => 'Unit not available for this vegetable type'
                ], 400);
            }
        }

        // Create preorder with unit data (immutable unit fields for audit trail)
        $preorderData = $request->only([
            'consumer_id', 'product_id', 'seller_id', 'quantity', 
            'expected_availability_date', 'variation_type', 'variation_name',
            'unit_key'
        ]);
        
        // Copy unit_price & unit_weight_kg at time of preorder creation (business rule: immutable)
        $preorderData['unit_price'] = $request->unit_price;
        $preorderData['unit_weight_kg'] = $request->unit_weight_kg;
        
        // Calculate reserved quantity and subtotal
        $preorderData['reserved_qty'] = $request->quantity * $request->unit_weight_kg;
        $preorderData['status'] = 'pending';
        $preorderData['version'] = 1; // Start with version 1 for optimistic locking

        $preorder = Preorder::create($preorderData);

        // Dispatch event for real-time notifications
        event(new PreorderCreated($preorder));

        return response()->json([
            'message' => 'Preorder created successfully',
            'preorder' => $preorder->load(['product', 'consumer', 'seller']),
            'subtotal' => $preorder->getSubtotalAttribute(), // Include calculated subtotal
            'total_weight_kg' => $preorder->getTotalWeightKgAttribute(), // Include total weight
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
        $product = Product::findOrFail($productId);
        
        // Check if product has low stock (≤ 2kg total - ONLY Premium, Type A, Type B)
        $totalStock = ($product->premium_stock_kg ?? 0) + 
                     ($product->type_a_stock_kg ?? 0) + 
                     ($product->type_b_stock_kg ?? 0);
        
        if ($totalStock > 2) {
            return response()->json([
                'eligible' => false,
                'message' => 'Product has sufficient stock',
                'total_stock' => $totalStock,
                'variations' => []
            ]);
        }
        
        // Check if there's an active crop schedule
        $cropSchedule = $product->cropSchedules()
            ->where('is_active', true)
            ->whereNotNull('expected_harvest_start')
            ->first();
            
        if (!$cropSchedule) {
            return response()->json([
                'eligible' => false,
                'message' => 'No active crop schedule found',
                'total_stock' => $totalStock,
                'variations' => []
            ]);
        }
        
        // Get estimated harvest from crop schedule (total kg expected)
        $estimatedHarvestKg = (float) ($cropSchedule->quantity_estimate ?? 0);
        
        // Calculate reserved quantities across all variations
        $reservedPremium = (float) $product->getReservedQuantity('premium');
        $reservedTypeA   = (float) $product->getReservedQuantity('type_a');
        $reservedTypeB   = (float) $product->getReservedQuantity('type_b');
        $totalReserved   = $reservedPremium + $reservedTypeA + $reservedTypeB;
        $availableTotal  = max(0, $estimatedHarvestKg - $totalReserved);

        // Determine vegetable-available units (ignore seller toggles for preorder)
        $vegetableSlug = $product->getVegetableSlug();
        $unitsInfo = UnitConversion::getAvailableUnitsWithInfo($vegetableSlug);
        $unitKeys = empty($unitsInfo)
            ? ['kg', 'sack', 'small_sack', 'packet', 'tali', 'pieces']
            : array_map(function ($u) { return $u['key']; }, $unitsInfo);

        // Build variations array - ALWAYS show Premium, Type A, Type B (capacity is the same total)
        $variations = [];

        // Premium variation
        $premiumPrice = (float) ($product->premium_price_per_kg ?? $product->price_per_kg ?? 0);
        $variations[] = [
            'type' => 'premium',
            'name' => 'Premium',
            'estimated_harvest_kg' => round($estimatedHarvestKg, 2),
            'reserved_kg' => round($totalReserved, 2),
            'available_kg' => round($availableTotal, 2),
            'price_per_kg' => $premiumPrice,
            'units' => $this->getUnitOptions($product, $unitKeys, 'premium'),
        ];

        // Type A variation
        $typeAPrice = (float) ($product->type_a_price_per_kg ?? $product->price_per_kg ?? 0);
        $variations[] = [
            'type' => 'type_a',
            'name' => 'Type A',
            'estimated_harvest_kg' => round($estimatedHarvestKg, 2),
            'reserved_kg' => round($totalReserved, 2),
            'available_kg' => round($availableTotal, 2),
            'price_per_kg' => $typeAPrice,
            'units' => $this->getUnitOptions($product, $unitKeys, 'type_a'),
        ];

        // Type B variation
        $typeBPrice = (float) ($product->type_b_price_per_kg ?? $product->price_per_kg ?? 0);
        $variations[] = [
            'type' => 'type_b',
            'name' => 'Type B',
            'estimated_harvest_kg' => round($estimatedHarvestKg, 2),
            'reserved_kg' => round($totalReserved, 2),
            'available_kg' => round($availableTotal, 2),
            'price_per_kg' => $typeBPrice,
            'units' => $this->getUnitOptions($product, $unitKeys, 'type_b'),
        ];
        
        return response()->json([
            'eligible' => true,
            'harvest_date' => $cropSchedule->expected_harvest_start,
            'estimated_total_harvest_kg' => $estimatedHarvestKg,
            'variations' => $variations,
            'message' => 'Product is eligible for preorders'
        ]);
    }
    

    /**
     * Get individual preorder details with unit fields
     */
    public function show(Request $request, $id)
    {
        $preorder = Preorder::with(['product', 'consumer', 'seller', 'harvest', 'statusUpdater'])
            ->findOrFail($id);

        // Check if user has access to this preorder (seller or consumer)
        $user = $request->user();
        if ($preorder->seller_id !== $user->id && $preorder->consumer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized access to preorder'
            ], 403);
        }

        // Enhance preorder data with calculated fields
        $preorderData = [
            'id' => $preorder->id,
            'consumer_id' => $preorder->consumer_id,
            'seller_id' => $preorder->seller_id,
            'product_id' => $preorder->product_id,
            'quantity' => $preorder->quantity,
            'expected_availability_date' => $preorder->expected_availability_date,
            
            // Unit fields (immutable for audit trail)
            'variation_type' => $preorder->variation_type,
            'variation_name' => $preorder->variation_name,
            'unit_key' => $preorder->unit_key,
            'unit_display_name' => $preorder->unit_display_name,
            'unit_weight_kg' => $preorder->unit_weight_kg,
            'unit_price' => $preorder->unit_price,
            
            // Status and tracking fields
            'status' => $preorder->status,
            'harvest_date' => $preorder->harvest_date,
            'harvest_date_ref' => $preorder->harvest_date_ref,
            'reserved_qty' => $preorder->reserved_qty,
            'allocated_qty' => $preorder->allocated_qty,
            'notes' => $preorder->notes,
            'version' => $preorder->version,
            
            // Timestamps
            'created_at' => $preorder->created_at,
            'matched_at' => $preorder->matched_at,
            'ready_at' => $preorder->ready_at,
            'status_updated_at' => $preorder->status_updated_at,
            'status_updated_by' => $preorder->status_updated_by,
            
            // Related data with proper structure
            'product' => $preorder->product ? [
                'product_id' => $preorder->product->product_id,
                'product_name' => $preorder->product->product_name,
                'stock_kg' => $preorder->product->stock_kg,
                'price_per_kg' => $preorder->product->price_per_kg,
                'image_url' => $preorder->product->image_url,
            ] : null,
            'consumer' => $preorder->consumer ? [
                'id' => $preorder->consumer->id,
                'name' => $preorder->consumer->name,
                'phone' => $preorder->consumer->phone ?? null,
            ] : null,
            'seller' => $preorder->seller ? [
                'id' => $preorder->seller->id,
                'name' => $preorder->seller->name,
                'phone' => $preorder->seller->phone ?? null,
            ] : null,
            'harvest' => $preorder->harvest,
            'statusUpdater' => $preorder->statusUpdater ? [
                'id' => $preorder->statusUpdater->id,
                'name' => $preorder->statusUpdater->name,
            ] : null,
            'fulfilled_order' => $preorder->fulfilledOrder,
            
            // Calculated fields
            'subtotal' => $preorder->getSubtotalAttribute(),
            'total_weight_kg' => $preorder->getTotalWeightKgAttribute(),
            
            // Business logic checks
            'can_be_cancelled' => $preorder->canBeCancelled(),
            'can_be_fulfilled' => $preorder->canBeFulfilled(),
            'can_be_matched' => $preorder->canBeMatched(),
            'is_ready_for_fulfillment' => $preorder->isReadyForFulfillment(),
        ];

        return response()->json([
            'data' => $preorderData,
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
     * Get seller's preorders grouped by variation & unit
     */
    public function sellerPreorders(Request $request)
    {
        $sellerId = $request->user()->id;
        
        $preorders = Preorder::with(['product', 'consumer', 'harvest'])
            ->where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Group preorders by variation & unit as per business requirements
        $groupedPreorders = $preorders->groupBy(function ($preorder) {
            return $preorder->variation_type . '_' . $preorder->unit_key;
        })->map(function ($group) {
            return [
                'variation_type' => $group->first()->variation_type,
                'variation_name' => $group->first()->variation_name,
                'unit_key' => $group->first()->unit_key,
                'unit_display_name' => $group->first()->unit_display_name,
                'total_quantity' => $group->sum('quantity'),
                'total_weight_kg' => $group->sum('reserved_qty'),
                'total_subtotal' => $group->sum(function ($p) { return $p->getSubtotalAttribute(); }),
                'status_counts' => $group->countBy('status'),
                'preorders' => $group->map(function ($preorder) {
                    return [
                        'id' => $preorder->id,
                        // Buyer
                        'consumer_id' => $preorder->consumer_id,
                        'consumer_name' => $preorder->consumer->name ?? 'Unknown',
                        'consumer' => [
                            'id' => $preorder->consumer_id,
                            'name' => $preorder->consumer->name ?? 'Unknown',
                            'phone' => $preorder->consumer->phone ?? null,
                        ],
                        // Product
                        'product_id' => $preorder->product_id,
                        'product_name' => $preorder->product->product_name ?? 'Unknown Product',
                        'product' => [
                            'product_id' => $preorder->product_id,
                            'product_name' => $preorder->product->product_name ?? 'Unknown Product',
                            'image_url' => $preorder->product->image_url ?? null,
                            'price_per_kg' => $preorder->product->price_per_kg ?? null,
                        ],
                        // Unit & Variation
                        'variation_type' => $preorder->variation_type,
                        'variation_name' => $preorder->variation_name,
                        'unit_key' => $preorder->unit_key,
                        'unit_weight_kg' => $preorder->unit_weight_kg,
                        'unit_price' => $preorder->unit_price,
                        // Quantities
                        'quantity' => $preorder->quantity,
                        'reserved_qty' => $preorder->reserved_qty,
                        'allocated_qty' => $preorder->allocated_qty,
                        // Totals
                        'subtotal' => $preorder->getSubtotalAttribute(),
                        // Dates & Status
                        'expected_availability_date' => $preorder->expected_availability_date,
                        'status' => $preorder->status,
                        'created_at' => $preorder->created_at,
                        'matched_at' => $preorder->matched_at,
                        'ready_at' => $preorder->ready_at,
                        'harvest_date_ref' => $preorder->harvest_date_ref,
                        'notes' => $preorder->notes,
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'data' => $groupedPreorders,
            'message' => 'Seller preorders retrieved successfully (grouped by variation & unit)'
        ]);
    }

    /**
     * Get consumer's preorders
     */
    public function consumerPreorders(Request $request)
    {
        $consumerId = $request->user()->id;
        
        $preorders = Preorder::with(['product', 'seller', 'consumer', 'harvest', 'statusUpdater'])
            ->where('consumer_id', $consumerId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Ensure seller and product data is included
        $preorders = $preorders->map(function ($preorder) {
            return [
                'id' => $preorder->id,
                'consumer_id' => $preorder->consumer_id,
                'product_id' => $preorder->product_id,
                'seller_id' => $preorder->seller_id,
                'quantity' => $preorder->quantity,
                'expected_availability_date' => $preorder->expected_availability_date,
                'created_at' => $preorder->created_at,
                'variation_type' => $preorder->variation_type,
                'variation_name' => $preorder->variation_name,
                'unit_key' => $preorder->unit_key,
                'unit_weight_kg' => $preorder->unit_weight_kg,
                'unit_price' => $preorder->unit_price,
                'status' => $preorder->status,
                'harvest_date' => $preorder->harvest_date,
                'allocated_qty' => $preorder->allocated_qty,
                'harvest_date_ref' => $preorder->harvest_date_ref,
                'matched_at' => $preorder->matched_at,
                'ready_at' => $preorder->ready_at,
                'status_updated_at' => $preorder->status_updated_at,
                'status_updated_by' => $preorder->status_updated_by,
                'version' => $preorder->version,
                'product' => $preorder->product ? [
                    'product_id' => $preorder->product->product_id,
                    'product_name' => $preorder->product->product_name,
                    'stock_kg' => $preorder->product->stock_kg,
                    'price_per_kg' => $preorder->product->price_per_kg,
                    'image_url' => $preorder->product->image_url,
                ] : null,
                'seller' => $preorder->seller ? [
                    'id' => $preorder->seller->id,
                    'name' => $preorder->seller->name,
                    'phone' => $preorder->seller->phone ?? null,
                ] : null,
                'consumer' => $preorder->consumer ? [
                    'id' => $preorder->consumer->id,
                    'name' => $preorder->consumer->name,
                    'phone' => $preorder->consumer->phone ?? null,
                ] : null,
                'harvest' => $preorder->harvest,
                'statusUpdater' => $preorder->statusUpdater ? [
                    'id' => $preorder->statusUpdater->id,
                    'name' => $preorder->statusUpdater->name,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $preorders,
            'message' => 'Consumer preorders retrieved successfully'
        ]);
    }

    /**
     * Fulfill a preorder (atomic conversion to order)
     */
    public function fulfill(Request $request, $preorderId)
    {
        $request->validate([
            'expected_version' => 'nullable|integer|min:1', // For optimistic locking
        ]);

        $preorder = Preorder::findOrFail($preorderId);
        
        // Validate seller owns this preorder
        if ($preorder->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$preorder->canBeFulfilled()) {
            return response()->json([
                'message' => 'Preorder cannot be fulfilled at this time',
                'current_status' => $preorder->status,
                'allocated_qty' => $preorder->allocated_qty
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Optimistic locking check (business rule: prevent race conditions)
            $expectedVersion = $request->expected_version ?? $preorder->version;
            if ($preorder->version !== $expectedVersion) {
                throw new \Exception('Preorder has been modified by another process. Please refresh and try again.');
            }

            // Re-check fulfillment eligibility within transaction
            if (!$preorder->canBeFulfilled()) {
                throw new \Exception('Preorder is no longer eligible for fulfillment');
            }

            // Update preorder status with optimistic locking
            $preorder->updateStatus('fulfilled', $expectedVersion, $request->user()->id);

            // Create order from preorder (atomic operation)
            $order = $this->createOrderFromPreorder($preorder);

            // Update product stock (atomic operation)
            $this->updateProductStockFromPreorder($preorder);

            // Dispatch event for real-time notifications
            event(new PreorderFulfilled($preorder, $order));

            DB::commit();

            return response()->json([
                'message' => 'Preorder fulfilled successfully',
                'order' => $order->load(['items', 'user']),
                'preorder' => $preorder->fresh(),
                'new_version' => $preorder->version
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Return 409 Conflict for race conditions (business rule)
            if (str_contains($e->getMessage(), 'modified by another process')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'current_version' => $preorder->fresh()->version
                ], 409);
            }
            
            return response()->json([
                'message' => 'Failed to fulfill preorder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a preorder with conflict handling
     */
    public function cancel(Request $request, $preorderId)
    {
        $request->validate([
            'expected_version' => 'nullable|integer|min:1', // For optimistic locking
            'reason' => 'nullable|string|max:255', // Cancellation reason
        ]);

        $preorder = Preorder::findOrFail($preorderId);
        
        // Validate user has access (seller or consumer can cancel)
        $user = $request->user();
        if ($preorder->seller_id !== $user->id && $preorder->consumer_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$preorder->canBeCancelled()) {
            return response()->json([
                'message' => 'Preorder cannot be cancelled at this time',
                'current_status' => $preorder->status,
                'harvest_published' => $preorder->harvest && $preorder->harvest->published
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Optimistic locking check (business rule: prevent race conditions)
            $expectedVersion = $request->expected_version ?? $preorder->version;
            if ($preorder->version !== $expectedVersion) {
                throw new \Exception('Preorder has been modified by another process. Please refresh and try again.');
            }

            // Re-check cancellation eligibility within transaction
            if (!$preorder->canBeCancelled()) {
                throw new \Exception('Preorder is no longer eligible for cancellation');
            }

            // Update preorder status with optimistic locking
            $preorder->updateStatus('cancelled', $expectedVersion, $user->id);
            
            // Add cancellation reason if provided
            if ($request->reason) {
                $preorder->notes = ($preorder->notes ? $preorder->notes . "\n" : '') . 
                                 'Cancellation reason: ' . $request->reason;
                $preorder->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Preorder cancelled successfully',
                'preorder' => $preorder->fresh(),
                'new_version' => $preorder->version
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Return 409 Conflict for race conditions (business rule)
            if (str_contains($e->getMessage(), 'modified by another process')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'current_version' => $preorder->fresh()->version
                ], 409);
            }
            
            return response()->json([
                'message' => 'Failed to cancel preorder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept a preorder and immediately convert to an order (same behavior as fulfill)
     */
    public function accept(Request $request, $preorderId)
    {
        $request->validate([
            'expected_version' => 'nullable|integer|min:1',
        ]);

        $preorder = Preorder::findOrFail($preorderId);

        // Validate seller owns this preorder
        if ($preorder->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Accept allowed from pending/reserved/ready
        if (!in_array($preorder->status, ['pending', 'reserved', 'ready'])) {
            return response()->json([
                'message' => 'Preorder cannot be accepted at this time',
                'current_status' => $preorder->status
            ], 400);
        }

        try {
            DB::beginTransaction();

            $expectedVersion = $request->expected_version ?? $preorder->version;

            // If pending/reserved, mark as ready first
            if (in_array($preorder->status, ['pending', 'reserved'])) {
                $preorder->updateStatus('ready', $expectedVersion, $request->user()->id);
                $preorder->ready_at = now();
                $preorder->save();
                // After updating status, the version has incremented, so skip version check on next update
                $expectedVersion = null; // ✅ Skip version check for second update since we're in transaction
            }

            // Now fulfill the preorder (convert to order)
            // Use null for expectedVersion if we just updated above, since we're in a transaction
            $preorder->updateStatus('fulfilled', $expectedVersion, $request->user()->id);

            // Create order from preorder and update stocks
            $order = $this->createOrderFromPreorder($preorder);
            $this->updateProductStockFromPreorder($preorder);

            event(new PreorderFulfilled($preorder, $order));

            DB::commit();

            return response()->json([
                'message' => 'Preorder accepted and converted to order successfully',
                'order' => $order->load(['items', 'user']),
                'preorder' => $preorder->fresh(),
                'new_version' => $preorder->version
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to accept preorder: ' . $e->getMessage()
            ], 500);
        }
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
     * Get product variations with unit options using enhanced unit system
     */
    private function getProductVariationsWithUnits(Product $product): array
    {
        $variations = [];
        
        // Regular variation
        $variations[] = [
            'id' => 'regular',
            'name' => 'Regular',
            'type' => 'regular',
            'stock_kg' => $product->stock_kg ?? 0,
            'price_per_kg' => $product->price_per_kg ?? 0,
            'units' => $product->getUnitsForVariation('regular')
        ];

        // Premium variation
        if (($product->premium_stock_kg ?? 0) > 0) {
            $variations[] = [
                'id' => 'premium',
                'name' => 'Premium',
                'type' => 'premium',
                'stock_kg' => $product->premium_stock_kg,
                'price_per_kg' => $product->premium_price_per_kg ?? $product->price_per_kg ?? 0,
                'units' => $product->getUnitsForVariation('premium')
            ];
        }

        // Type A variation
        if (($product->type_a_stock_kg ?? 0) > 0) {
            $variations[] = [
                'id' => 'type_a',
                'name' => 'Type A',
                'type' => 'type_a',
                'stock_kg' => $product->type_a_stock_kg,
                'price_per_kg' => $product->type_a_price_per_kg ?? $product->price_per_kg ?? 0,
                'units' => $product->getUnitsForVariation('type_a')
            ];
        }

        // Type B variation
        if (($product->type_b_stock_kg ?? 0) > 0) {
            $variations[] = [
                'id' => 'type_b',
                'name' => 'Type B',
                'type' => 'type_b',
                'stock_kg' => $product->type_b_stock_kg,
                'price_per_kg' => $product->type_b_price_per_kg ?? $product->price_per_kg ?? 0,
                'units' => $product->getUnitsForVariation('type_b')
            ];
        }

        return $variations;
    }

    /**
     * Get product variations with unit options (legacy method for backward compatibility)
     */
    private function getProductVariations(Product $product): array
    {
        return $this->getProductVariationsWithUnits($product);
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

        // Create order item from preorder data with immutable unit fields (business rule)
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $preorder->product_id,
            'seller_id' => $preorder->seller_id,
            'product_name' => $preorder->product->product_name ?? 'Unknown Product',
            'price' => $preorder->unit_price, // Use immutable unit_price from preorder
            'quantity' => $preorder->quantity,
            'unit' => $preorder->unit_key,
            'image_url' => $preorder->product->image_url ?? null,
            
            // Variation data (immutable for audit trail)
            'variation_type' => $preorder->variation_type,
            'variation_name' => $preorder->variation_name,
            
            // Unit fields (immutable for audit trail - business rule)
            'unit_key' => $preorder->unit_key,
            'unit_weight_kg' => $preorder->unit_weight_kg,
            'allocated_qty' => $preorder->allocated_qty,
            'harvest_date_ref' => $preorder->harvest_date_ref,
            
            // Weight and pricing data
            'estimated_weight_kg' => $preorder->getTotalWeightKgAttribute(),
            'actual_weight_kg' => $preorder->allocated_qty ?? $preorder->getTotalWeightKgAttribute(),
            'price_per_kg_at_order' => $preorder->unit_price, // Use immutable unit_price
            'estimated_price' => $preorder->getSubtotalAttribute(),
            'reserved' => true, // Mark as reserved since it came from preorder
            'seller_verification_status' => 'pending',
            'seller_notes' => null,
            'seller_confirmed_at' => null,
        ]);

        // Update product stock (reduce by reserved quantity)
        $this->updateProductStockFromPreorder($preorder);

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
     * Update product stock after preorder fulfillment (atomic operation)
     */
    private function updateProductStockFromPreorder(Preorder $preorder)
    {
        $product = $preorder->product;
        if (!$product) return;

        // Use allocated quantity if available, otherwise use reserved quantity
        $allocatedWeight = $preorder->allocated_qty ?? $preorder->getTotalWeightKgAttribute();

        // Update stock based on variation type (atomic operation)
        switch ($preorder->variation_type) {
            case 'premium':
                $product->decrement('premium_stock_kg', $allocatedWeight);
                break;
            case 'type_a':
                $product->decrement('type_a_stock_kg', $allocatedWeight);
                break;
            case 'type_b':
                $product->decrement('type_b_stock_kg', $allocatedWeight);
                break;
            case 'regular':
            default:
                $product->decrement('stock_kg', $allocatedWeight);
                break;
        }
    }



    /**
     * Get product stock information including reserved quantities
     */
    public function getStockInfo($productId)
    {
        $product = Product::with(['preorders' => function ($query) {
            $query->whereIn('status', ['pending', 'confirmed']);
        }])->findOrFail($productId);

        $stockInfo = $product->getVariationsWithReservedQuantities();

        return response()->json([
            'product_id' => $product->product_id,
            'product_name' => $product->product_name,
            'variations' => $stockInfo,
            'expected_harvest' => $product->getExpectedHarvestQuantity(),
            'message' => 'Stock information retrieved successfully'
        ]);
    }

}
