<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CropSchedule;
use App\Models\Harvest;
use App\Models\Product;
use App\Events\HarvestPublished;
use App\Events\ProductStockUpdated;
use App\Events\ProductSalesUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HarvestController extends Controller
{
    /**
     * Display a listing of all harvests (admin view).
     */
    public function index(Request $request)
    {
        $harvests = Harvest::with(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier'])
            ->orderBy('harvested_at', 'desc')
            ->get();

        return response()->json($harvests);
    }

    /**
     * Store a new harvest for a specific crop schedule (admin can create for any schedule).
     */
    public function storeForSchedule(Request $request, CropSchedule $cropSchedule)
    {
        $actor = $request->user();

        if (!($actor instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'harvested_at' => 'required|date',
            'yield_qty' => 'required|numeric|min:0',
            'yield_unit' => 'required|string|in:kg,g,bunch,tray,piece',
            'grade' => 'nullable|string|in:A,B,C,reject',
            'waste_qty' => 'nullable|numeric|min:0',
            'moisture_pct' => 'nullable|numeric|min:0|max:100',
            'photos_json' => 'nullable|array',
            'photos_json.*' => 'string',
        ]);

        $validated['crop_schedule_id'] = $cropSchedule->id;
        $validated['created_by_type'] = 'admin';
        $validated['created_by_id'] = $actor->id;
        $validated['lot_code'] = 'H' . date('Ymd') . '-' . \Str::random(6);

        $harvest = Harvest::create($validated);

        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']), 201);
    }

    /**
     * Display the specified harvest.
     */
    public function show(Request $request, Harvest $harvest)
    {
        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']));
    }

    /**
     * Update the specified harvest.
     */
    public function update(Request $request, Harvest $harvest)
    {
        $validated = $request->validate([
            'harvested_at' => 'nullable|date',
            'yield_qty' => 'nullable|numeric|min:0',
            'yield_unit' => 'nullable|string|in:kg,g,bunch,tray,piece',
            'grade' => 'nullable|string|in:A,B,C,reject',
            'waste_qty' => 'nullable|numeric|min:0',
            'moisture_pct' => 'nullable|numeric|min:0|max:100',
            'photos_json' => 'nullable|array',
            'photos_json.*' => 'string',
        ]);

        $harvest->update($validated);

        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']));
    }

    /**
     * Delete the specified harvest.
     */
    public function destroy(Request $request, Harvest $harvest)
    {
        $harvest->delete();

        return response()->json(['message' => 'Harvest deleted successfully']);
    }

    /**
     * Verify a harvest.
     */
    public function verify(Request $request, Harvest $harvest)
    {
        $actor = $request->user();

        if (!($actor instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $harvest->update([
            'verified' => true,
            'verified_at' => now(),
            'verified_by' => $actor->id,
        ]);

        // 🔄 AUTOMATICALLY UPDATE STOCK when admin verifies harvest
        $this->updateProductStock($harvest);

        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']));
    }

    /**
     * Update product stock when harvest is verified
     */
    private function updateProductStock(Harvest $harvest)
    {
        try {
            // Get the product from the harvest's crop schedule
            $product = $harvest->cropSchedule->product;
            
            if (!$product) {
                Log::warning('Harvest verification: No product found for crop schedule', [
                    'harvest_id' => $harvest->id,
                    'crop_schedule_id' => $harvest->crop_schedule_id
                ]);
                return;
            }

            $harvestWeight = (float) $harvest->actual_weight_kg;
            $variationType = $harvest->variation_type;

            if (!$harvestWeight || $harvestWeight <= 0) {
                Log::warning('Harvest verification: Invalid harvest weight', [
                    'harvest_id' => $harvest->id,
                    'actual_weight_kg' => $harvest->actual_weight_kg
                ]);
                return;
            }

            // Update total stock
            $currentTotalStock = (float) $product->stock_kg;
            $newTotalStock = $currentTotalStock + $harvestWeight;

            // Update variation-specific stock
            $updateData = ['stock_kg' => $newTotalStock];
            
            if ($variationType && in_array($variationType, ['premium', 'type_a', 'type_b'])) {
                $variationField = $variationType . '_stock_kg';
                if (isset($product->$variationField)) {
                    $currentVariationStock = (float) $product->$variationField;
                    $newVariationStock = $currentVariationStock + $harvestWeight;
                    $updateData[$variationField] = $newVariationStock;
                }
            }

            // Update the product
            $product->update($updateData);

            // Broadcast stock updates to frontend
            try {
                broadcast(new ProductStockUpdated($product));
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast product stock update', [
                    'error' => $e->getMessage(),
                    'product_id' => $product->product_id
                ]);
            }

            Log::info('Stock updated after harvest verification', [
                'harvest_id' => $harvest->id,
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'harvest_weight' => $harvestWeight,
                'variation_type' => $variationType,
                'old_total_stock' => $currentTotalStock,
                'new_total_stock' => $newTotalStock,
                'updated_fields' => array_keys($updateData)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update product stock after harvest verification', [
                'harvest_id' => $harvest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Publish a harvest (admin can publish any harvest).
     */
    public function publish(Request $request, Harvest $harvest)
    {
        $actor = $request->user();

        if (!($actor instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,product_id',
        ]);

        $harvest->update([
            'published' => true,
            'published_at' => now(),
            'product_id' => $validated['product_id'],
        ]);

        // Update the product's image with the harvest image if available
        if ($harvest->harvest_image) {
            $product = \App\Models\Product::find($validated['product_id']);
            if ($product) {
                $product->update([
                    'image_url' => $harvest->harvest_image
                ]);
            }
        }

        // Emit harvest:published event for matching job
        event(new HarvestPublished($harvest));

        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']));
    }
}
