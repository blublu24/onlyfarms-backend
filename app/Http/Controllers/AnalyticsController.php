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

    // 📅 Daily Sales (last 7 days)
    public function dailySales()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->where('oi.created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(oi.created_at) as date, SUM(oi.price * oi.quantity) as total_sales')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    // 📊 Weekly Sales (last 4 weeks)
    public function weeklySales()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->where('oi.created_at', '>=', now()->subWeeks(4))
            ->selectRaw('YEARWEEK(oi.created_at) as week, SUM(oi.price * oi.quantity) as total_sales')
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        return response()->json($data);
    }

    // 📈 Monthly Sales Detailed (last 6 months)
    public function monthlySalesDetailed()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->where('oi.created_at', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(oi.created_at) as year, MONTH(oi.created_at) as month, SUM(oi.price * oi.quantity) as total_sales')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }

    // 🏆 Top Seller
    public function topSeller()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('sellers as s', 'oi.seller_id', '=', 's.user_id')
            ->join('users as u', 's.user_id', '=', 'u.id')
            ->where('o.status', 'completed')
            ->selectRaw('s.shop_name, u.name, u.avatar as profile_image, SUM(oi.price * oi.quantity) as revenue, COUNT(DISTINCT o.id) as orders')
            ->groupBy('s.id', 's.shop_name', 'u.name', 'u.avatar')
            ->orderByDesc('revenue')
            ->first();

        return response()->json($data);
    }

    // ⭐ Top Rated Product
    public function topRatedProduct()
    {
        $data = DB::table('products as p')
            ->leftJoin('product_reviews as r', 'p.product_id', '=', 'r.product_id')
            ->selectRaw('p.product_name, p.price_kg, p.price_bunches, p.image_url, p.full_image_url, 
                        AVG(r.rating) as average_rating, COUNT(r.id) as total_reviews')
            ->groupBy('p.product_id', 'p.product_name', 'p.price_kg', 'p.price_bunches', 'p.image_url', 'p.full_image_url')
            ->having('total_reviews', '>', 0)
            ->orderByDesc('average_rating')
            ->orderByDesc('total_reviews')
            ->first();

        return response()->json($data);
    }
}
