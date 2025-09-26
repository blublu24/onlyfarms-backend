<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\MOdels\Order;

class ReviewController extends Controller
{
    /**
     * Get all reviews for a product
     */
    public function index($productId)
    {
        $reviews = Review::with('user')
            ->where('product_id', $productId)
            ->latest()
            ->get();

        return response()->json($reviews);
    }

    /**
     * Store a new review
     */
    public function store(Request $request, $productId, $orderItemId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $userId = Auth::id();

        // Ensure user owns the order_item
        $orderItem = OrderItem::where('id', $orderItemId)
            ->where('product_id', $productId)
            ->firstOrFail();

        // Prevent duplicate reviews on the same order item
        if (Review::where('order_item_id', $orderItem->id)->exists()) {
            return response()->json(['message' => 'You already reviewed this item'], 400);
        }

        $review = Review::create([
            'product_id' => $productId,
            'user_id' => $userId,
            'order_item_id' => $orderItem->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json($review, 201);
    }

    /**
     * Update an existing review
     */
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'rating' => 'integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $review->update($request->only(['rating', 'comment']));

        return response()->json($review);
    }

    /**
     * Delete a review
     */
    public function destroy($id)
    {
        $review = Review::findOrFail($id);

        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }

    public function reviewableItems(Order $order)
    {
        $userId = auth()->id();

        // Ensure the order belongs to the logged-in user
        if ($order->user_id !== $userId) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'This order does not belong to the authenticated user.'
            ], 403);
        }

        // ✅ Ensure order is completed before allowing reviews
        if ($order->status !== 'completed') {
            return response()->json([
                'error' => 'Not Allowed',
                'message' => 'You can only review items from completed orders.'
            ], 400);
        }

        // Fetch all order items, include review if exists
        $items = $order->orderItems()
            ->with(['product', 'review'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => $item->product,
                    'reviewed' => $item->review !== null, // true if already reviewed
                    'review' => $item->review, // full review if exists
                ];
            });

        return response()->json($items);
    }

}
