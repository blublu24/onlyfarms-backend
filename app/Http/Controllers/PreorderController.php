<?php

namespace App\Http\Controllers;

use App\Models\Preorder;
use Illuminate\Http\Request;

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
            'quantity'    => 'required|integer|min:1',
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
}
