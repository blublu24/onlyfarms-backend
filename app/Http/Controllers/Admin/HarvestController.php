<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CropSchedule;
use App\Models\Harvest;
use Illuminate\Http\Request;

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

        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']));
    }

    /**
     * Publish a harvest (admin can publish any harvest).
     */
    public function publish(Request $request, Harvest $harvest)
    {
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

        return response()->json($harvest->load(['cropSchedule.product', 'cropSchedule.seller', 'product', 'verifier']));
    }
}
