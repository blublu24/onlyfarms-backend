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
        try {
            $data = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->join('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->selectRaw('s.shop_name, u.name, u.avatar as profile_image, SUM(oi.price * oi.quantity) as revenue, COUNT(DISTINCT o.id) as orders')
                ->groupBy('s.id', 's.shop_name', 'u.name', 'u.avatar')
                ->orderByDesc('revenue')
                ->first();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No top seller data available'], 200);
        }
    }

    // ⭐ Top Rated Product
    public function topRatedProduct()
    {
        try {
            $data = DB::table('products as p')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('p.ratings_count', '>', 0)
                ->selectRaw('p.product_name, p.price_kg, p.price_bunches, p.image_url, p.full_image_url, 
                            s.shop_name, p.avg_rating as average_rating, p.ratings_count as total_reviews')
                ->orderByDesc('p.avg_rating')
                ->orderByDesc('p.ratings_count')
                ->first();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No rated products available'], 200);
        }
    }

    // 📊 Top Yearly Sales (last 12 months)
    public function yearlySales()
    {
        $data = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->where('oi.created_at', '>=', now()->subMonths(12))
            ->selectRaw('YEAR(oi.created_at) as year, SUM(oi.price * oi.quantity) as total_sales')
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        return response()->json($data);
    }

    // 🏆 Most Bought Products (top 10)
    public function mostBoughtProducts()
    {
        try {
            $data = DB::table('order_items as oi')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->selectRaw('p.product_id, p.product_name, p.image_url, p.full_image_url, 
                            s.shop_name, SUM(oi.quantity) as total_quantity_sold,
                            COUNT(DISTINCT o.id) as total_orders')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 'p.full_image_url', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->limit(10)
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No bought products available'], 200);
        }
    }

    // ⭐ Most Rated Products (top 10)
    public function mostRatedProducts()
    {
        try {
            $data = DB::table('products as p')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('p.ratings_count', '>', 0)
                ->selectRaw('p.product_id, p.product_name, p.image_url, p.full_image_url,
                            s.shop_name, p.avg_rating as average_rating, 
                            p.ratings_count as total_reviews')
                ->orderByDesc('p.avg_rating')
                ->orderByDesc('p.ratings_count')
                ->limit(10)
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No rated products available'], 200);
        }
    }

    // 📊 Daily Product Sales (individual products sold today)
    public function dailyProductSales()
    {
        try {
            $data = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->whereDate('oi.created_at', today())
                ->selectRaw('p.product_name, s.shop_name, SUM(oi.quantity) as total_quantity_sold, 
                            SUM(oi.price * oi.quantity) as total_sales_amount')
                ->groupBy('p.product_id', 'p.product_name', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No daily product sales available'], 200);
        }
    }

    // 📊 Weekly Product Sales (individual products sold this week)
    public function weeklyProductSales()
    {
        try {
            $data = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->where('oi.created_at', '>=', now()->subWeek())
                ->selectRaw('p.product_name, s.shop_name, SUM(oi.quantity) as total_quantity_sold, 
                            SUM(oi.price * oi.quantity) as total_sales_amount')
                ->groupBy('p.product_id', 'p.product_name', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No weekly product sales available'], 200);
        }
    }

    // 📊 Monthly Product Sales (individual products sold this month)
    public function monthlyProductSales()
    {
        try {
            $data = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->where('oi.created_at', '>=', now()->subMonth())
                ->selectRaw('p.product_name, s.shop_name, SUM(oi.quantity) as total_quantity_sold, 
                            SUM(oi.price * oi.quantity) as total_sales_amount')
                ->groupBy('p.product_id', 'p.product_name', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No monthly product sales available'], 200);
        }
    }

    // 📊 Yearly Product Sales (individual products sold this year)
    public function yearlyProductSales()
    {
        try {
            $data = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->where('oi.created_at', '>=', now()->subYear())
                ->selectRaw('p.product_name, s.shop_name, SUM(oi.quantity) as total_quantity_sold, 
                            SUM(oi.price * oi.quantity) as total_sales_amount')
                ->groupBy('p.product_id', 'p.product_name', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No yearly product sales available'], 200);
        }
    }
}
