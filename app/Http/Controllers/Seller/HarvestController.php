<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CropSchedule;
use App\Models\Harvest;
use App\Models\Seller;
use App\Models\User;
use App\Events\HarvestPublished;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HarvestController extends Controller
{
    /**
     * Display a listing of harvests for the authenticated seller.
     */
    public function index(Request $request)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seller = Seller::where('user_id', $actor->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        $harvests = Harvest::whereHas('cropSchedule', function ($query) use ($seller) {
            $query->where('seller_id', $seller->id);
        })
        ->with(['cropSchedule.product', 'product'])
        ->orderBy('harvested_at', 'desc')
        ->get();

        return response()->json($harvests);
    }

    /**
     * Store a new harvest for a specific crop schedule.
     */
    public function storeForSchedule(Request $request, CropSchedule $cropSchedule)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seller = Seller::where('user_id', $actor->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        // Verify the crop schedule belongs to this seller
        if ($cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'harvested_at' => 'required|date',
            'yield_qty' => 'required|numeric|min:0',
            'yield_unit' => 'required|string|in:kg,g,bunch,tray,piece',
            // Replace grade with variation classes
            'grade' => 'nullable|string|in:premium,type_a,type_b',
            // removed waste/moisture requirements
            'harvest_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'photos_json' => 'nullable|array',
            'photos_json.*' => 'string', // URLs or base64 strings
            
            // New fields for preorder matching
            'variation_type' => 'required|string|in:premium,type_a,type_b',
            'variation_name' => 'nullable|string|max:255',
            'unit_key' => 'required|string',
            'actual_weight_kg' => 'required|numeric|min:0',
            'quantity_units' => 'required|integer|min:1',
        ]);

        // Generate unique lot code
        $lotCode = 'H' . date('Ymd') . '-' . Str::random(6);

        $validated['crop_schedule_id'] = $cropSchedule->id;
        $validated['created_by_type'] = 'seller';
        $validated['created_by_id'] = $seller->id;
        $validated['lot_code'] = $lotCode;
        
        // Set available weight for allocation (initial allocation is 0)
        $validated['allocated_weight_kg'] = 0;
        $validated['available_weight_kg'] = $validated['actual_weight_kg'];

        // Handle harvest image upload
        if ($request->hasFile('harvest_image')) {
            $image = $request->file('harvest_image');
            $imageName = 'harvest_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('harvests', $imageName, 'public');
            $validated['harvest_image'] = $imagePath;
        }

        $harvest = Harvest::create($validated);

        return response()->json($harvest->load(['cropSchedule.product', 'product']), 201);
    }

    /**
     * Display the specified harvest.
     */
    public function show(Request $request, Harvest $harvest)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seller = Seller::where('user_id', $actor->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        // Verify the harvest belongs to this seller
        if ($harvest->cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($harvest->load(['cropSchedule.product', 'product', 'verifier']));
    }

    /**
     * Update the specified harvest.
     */
    public function update(Request $request, Harvest $harvest)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seller = Seller::where('user_id', $actor->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        // Verify the harvest belongs to this seller
        if ($harvest->cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Don't allow editing verified harvests
        if ($harvest->verified) {
            return response()->json(['message' => 'Cannot edit verified harvests.'], 403);
        }

        $validated = $request->validate([
            'harvested_at' => 'nullable|date',
            'yield_qty' => 'nullable|numeric|min:0',
            'yield_unit' => 'nullable|string|in:kg,g,bunch,tray,piece',
            'grade' => 'nullable|string|in:premium,type_a,type_b',
            'photos_json' => 'nullable|array',
            'photos_json.*' => 'string',
        ]);

        $harvest->update($validated);

        return response()->json($harvest->load(['cropSchedule.product', 'product', 'verifier']));
    }

    /**
     * Delete the specified harvest.
     */
    public function destroy(Request $request, Harvest $harvest)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seller = Seller::where('user_id', $actor->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        // Verify the harvest belongs to this seller
        if ($harvest->cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Don't allow deleting verified harvests
        if ($harvest->verified) {
            return response()->json(['message' => 'Cannot delete verified harvests.'], 403);
        }

        $harvest->delete();

        return response()->json(['message' => 'Harvest deleted successfully']);
    }

    /**
     * Publish a harvest (make it available for sale).
     */
    public function publish(Request $request, Harvest $harvest)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seller = Seller::where('user_id', $actor->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        // Verify the harvest belongs to this seller
        if ($harvest->cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only verified harvests can be published
        if (!$harvest->verified) {
            return response()->json(['message' => 'Harvest must be verified before publishing.'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,product_id',
        ]);

        $harvest->update([
            'published' => true,
            'published_at' => now(),
            'product_id' => $validated['product_id'],
        ]);

        // Emit harvest:published event for matching job
        event(new HarvestPublished($harvest));

        return response()->json($harvest->load(['cropSchedule.product', 'product', 'verifier']));
    }
}
