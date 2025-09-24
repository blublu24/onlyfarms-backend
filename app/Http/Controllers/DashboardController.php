<?php

namespace App\Http\Controllers;


use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get overall summary stats
     */
  public function summary()
{
    $totalProducts = Product::count();
    $totalOrders = Order::count();
    $totalUsers = User::count();

    // compute revenue: sum of product price * order quantity
    $totalRevenue = \DB::table('order_items')
   ->join('products', 'order_items.product_id', '=', 'products.product_id')
    ->selectRaw('SUM(order_items.price * order_items.quantity) as revenue')
    ->value('revenue');


    return response()->json([
        'total_products' => $totalProducts,
        'total_orders'   => $totalOrders,
        'total_revenue'  => $totalRevenue ?? 0,
        'total_users'    => $totalUsers,
    ]);
}


    /**
     * Get top 5 most purchased products
     */
    public function topPurchased()
    {
        $top = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.product_id')
            ->select(
                'products.product_id as id',
                'products.product_name as name',
                'products.image_url',
                DB::raw('SUM(order_items.quantity) as total_sold')
            )
            ->groupBy('products.product_id', 'products.product_name', 'products.image_url')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json($top);
    }
}
