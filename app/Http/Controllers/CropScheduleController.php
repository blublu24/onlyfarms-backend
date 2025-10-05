<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\CropSchedule;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;

class CropScheduleController extends Controller
{
    /**
     * Display a listing of crop schedules.
     * - Sellers (Users with Seller record): only their own schedules
     * - Admins: can view all schedules
     */
    public function index(Request $request)
    {
        $actor = $request->user(); // could be User or Admin due to Sanctum

        // Admins can view all crop schedules
        if ($actor instanceof Admin) {
            return CropSchedule::with(['product', 'seller'])->get();
        }

        // Sellers can view their own crop schedules
        if ($actor instanceof User) {
            $seller = Seller::where('user_id', $actor->id)->first();
            if ($seller) {
                return CropSchedule::where('seller_id', $seller->id)
                    ->with('product')
                    ->get();
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Display a specific crop schedule.
     * - Sellers: can view their own schedules
     * - Admins: can view any schedule
     */
    public function show(Request $request, CropSchedule $cropSchedule)
    {
        $actor = $request->user();

        // Admins can view any crop schedule
        if ($actor instanceof Admin) {
            return $cropSchedule->load(['product', 'seller']);
        }

        // Sellers can view their own crop schedules
        if ($actor instanceof User) {
            $seller = Seller::where('user_id', $actor->id)->first();
            if ($seller && $cropSchedule->seller_id === $seller->id) {
                return $cropSchedule->load('product');
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Store a new crop schedule (seller only).
     */
    public function store(Request $request)
    {
        $actor = $request->user();

        if (!($actor instanceof User)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure the user has a seller record
        $seller = Seller::where('user_id', $actor->id)->first();
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
            'status' => 'required|in:Planted,Growing,Ready for Harvest,Harvested',
            'notes' => 'nullable|string',
        ]);

        // Auto-fill crop_name if product_id is provided
        if (!empty($validated['product_id'])) {
            $product = Product::where('product_id', $validated['product_id'])->first();
            $validated['crop_name'] = $product ? $product->product_name : ($validated['crop_name'] ?? null);
        }

        $validated['seller_id'] = $seller->id;

        $schedule = CropSchedule::create($validated);

        return response()->json($schedule, 201);
    }

    /**
     * Update a crop schedule.
     * - Admin can update any
     * - Seller can update own
     */
    public function update(Request $request, CropSchedule $cropSchedule)
    {
        $actor = $request->user();

        // Admin can edit any schedule
        if ($actor instanceof Admin) {
            return $this->performUpdate($request, $cropSchedule);
        }

        // Seller can edit own schedule
        if ($actor instanceof User) {
            $seller = Seller::where('user_id', $actor->id)->first();
            if ($seller && $cropSchedule->seller_id === $seller->id) {
                return $this->performUpdate($request, $cropSchedule);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    private function performUpdate(Request $request, CropSchedule $cropSchedule)
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,product_id',
            'crop_name' => 'required_without:product_id|string',
            'planting_date' => 'nullable|date',
            'expected_harvest_start' => 'nullable|date',
            'expected_harvest_end' => 'nullable|date|after_or_equal:expected_harvest_start',
            'quantity_estimate' => 'nullable|integer',
            'quantity_unit' => 'nullable|string',
            'status' => 'nullable|in:Planted,Growing,Ready for Harvest,Harvested',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Auto-fill crop_name if product_id is updated
        if (!empty($validated['product_id'])) {
            $product = Product::where('product_id', $validated['product_id'])->first();
            $validated['crop_name'] = $product ? $product->product_name : ($validated['crop_name'] ?? null);
        }

        $cropSchedule->update($validated);

        return response()->json($cropSchedule);
    }

    /**
     * Delete a crop schedule.
     * - Admin can delete any
     * - Seller can delete own
     */
    public function destroy(Request $request, CropSchedule $cropSchedule)
    {
        $actor = $request->user();

        // Admin can delete any schedule
        if ($actor instanceof Admin) {
            $cropSchedule->delete();
            return response()->json(['message' => 'Schedule deleted successfully']);
        }

        // Seller can delete own schedule
        if ($actor instanceof User) {
            $seller = Seller::where('user_id', $actor->id)->first();
            if ($seller && $cropSchedule->seller_id === $seller->id) {
                $cropSchedule->delete();
                return response()->json(['message' => 'Schedule deleted successfully']);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
