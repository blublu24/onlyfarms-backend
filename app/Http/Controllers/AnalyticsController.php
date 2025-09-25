<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    // 📊 1. Monthly Sales
    public function monthlySales()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id') // join orders
            ->join('products as p', 'oi.product_id', '=', 'p.product_id')
            ->where('o.status', 'completed') // only completed orders
            ->selectRaw('YEAR(oi.created_at) as year, MONTH(oi.created_at) as month, p.product_name, SUM(oi.quantity) as total_sold')
            ->groupBy('year', 'month', 'p.product_name')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }

    // 📍 2. Origin of Produce
    public function origin()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.product_id')
            ->join('users as u', 'oi.seller_id', '=', 'u.id')
            ->join('addresses as a', 'u.id', '=', 'a.user_id')
            ->where('o.status', 'completed')
            ->selectRaw('p.product_name, a.address as origin, SUM(oi.quantity) as total_sold')
            ->groupBy('p.product_name', 'a.address')
            ->orderByDesc('total_sold')
            ->get();

        return response()->json($data);
    }

    // 💰 3. Revenue
    public function revenue()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->selectRaw('YEAR(oi.created_at) as year, MONTH(oi.created_at) as month, SUM(oi.price * oi.quantity) as total_revenue')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }

    // ⚖️ 4. Supply vs Demand
    public function supplyDemand()
    {
        $data = DB::table('products as p')
            ->leftJoin('order_items as oi', 'p.product_id', '=', 'oi.product_id')
            ->leftJoin('orders as o', 'oi.order_id', '=', 'o.id')
            ->selectRaw('YEAR(p.created_at) as year, MONTH(p.created_at) as month,
                         COUNT(p.product_id) as products_listed,
                         IFNULL(SUM(CASE WHEN o.status = "completed" THEN oi.quantity ELSE 0 END), 0) as products_sold')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }

    // ⭐ Top Products
    public function topProducts()
    {
        $data = DB::table('order_items as oi')
            ->join('products as p', 'oi.product_id', '=', 'p.product_id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id') // ✅ join orders
            ->where('o.status', 'completed') // ✅ only completed orders
            ->selectRaw('p.product_name, SUM(oi.quantity) as total_sold')
            ->groupBy('p.product_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    // 🌱 Seasonal Trends
    public function seasonalTrends()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id') // join orders
            ->join('products as p', 'oi.product_id', '=', 'p.product_id')
            ->where('o.status', 'completed') // only completed
            ->selectRaw('YEAR(oi.created_at) as year, MONTH(oi.created_at) as month, p.product_name, SUM(oi.quantity) as total_sold')
            ->groupBy('year', 'month', 'p.product_name')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }
}
