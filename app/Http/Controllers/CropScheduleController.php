<?php

namespace App\Http\Controllers;

use App\Models\CropSchedule;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;

class CropScheduleController extends Controller
{
    /**
     * Display a listing of crop schedules.
     * - Sellers: only their own schedules
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->is_seller) {
            // Join with seller_id from sellers table to ensure valid relation
            return CropSchedule::where('seller_id', $user->id)
                ->with('product')
                ->get();
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Store a new crop schedule
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->is_seller) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure the user has a seller record
        $seller = Seller::where('user_id', $user->id)->first();
        if (!$seller) {
            return response()->json(['message' => 'You must become a seller first.'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,product_id',
            'crop_name' => 'required_without:product_id|string',
            'planting_date' => 'required|date',
            'expected_harvest_start' => 'required|date',
            'expected_harvest_end' => 'required|date|after_or_equal:expected_harvest_start',
            'quantity_estimate' => 'nullable|integer',
            'quantity_unit' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Auto-fill crop_name if product_id is provided
        if (!empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            $validated['crop_name'] = $product->product_name;
        }

        $validated['seller_id'] = $seller->id; // use seller table's id, not user_id

        $schedule = CropSchedule::create($validated);

        return response()->json($schedule, 201);
    }

    /**
     * Update a crop schedule
     */
    public function update(Request $request, CropSchedule $cropSchedule)
    {
        $user = $request->user();

        // Ensure the user owns this schedule
        $seller = Seller::where('user_id', $user->id)->first();
        if (!$seller || $cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,product_id',
            'crop_name' => 'required_without:product_id|string',
            'planting_date' => 'nullable|date',
            'expected_harvest_start' => 'nullable|date',
            'expected_harvest_end' => 'nullable|date|after_or_equal:expected_harvest_start',
            'quantity_estimate' => 'nullable|integer',
            'quantity_unit' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Auto-fill crop_name if product_id is updated
        if (!empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            $validated['crop_name'] = $product->product_name;
        }

        $cropSchedule->update($validated);

        return response()->json($cropSchedule);
    }

    /**
     * Delete a crop schedule
     */
    public function destroy(Request $request, CropSchedule $cropSchedule)
    {
        $user = $request->user();

        $seller = Seller::where('user_id', $user->id)->first();
        if (!$seller || $cropSchedule->seller_id !== $seller->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cropSchedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
