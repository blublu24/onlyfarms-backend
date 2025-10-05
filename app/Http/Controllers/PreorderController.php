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
}
