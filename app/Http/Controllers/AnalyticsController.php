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
            $topSeller = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->join('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->selectRaw('
                    u.id as user_id,
                    u.name as seller_name,
                    u.profile_image,
                    s.shop_name,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(oi.quantity) as total_products_sold,
                    SUM(oi.price * oi.quantity) as total_revenue
                ')
                ->groupBy('u.id', 'u.name', 'u.profile_image', 's.shop_name')
                ->orderByDesc('total_orders')
                ->orderByDesc('total_revenue')
                ->first();

            if ($topSeller) {
                return response()->json([
                    'user_id' => $topSeller->user_id,
                    'name' => $topSeller->seller_name,
                    'profile_image' => $topSeller->profile_image,
                    'shop_name' => $topSeller->shop_name,
                    'orders' => $topSeller->total_orders,
                    'products_sold' => $topSeller->total_products_sold,
                    'revenue' => $topSeller->total_revenue
                ]);
            }

            return response()->json(['error' => 'No top seller data available'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No top seller data available', 'debug' => $e->getMessage()], 200);
        }
    }

    // ⭐ Top Rated Product
    public function topRatedProduct()
    {
        try {
            $topRatedProduct = DB::table('products as p')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('p.ratings_count', '>', 0)
                ->where('p.avg_rating', '>', 0)
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.price_kg,
                    p.price_bunches,
                    p.image_url,
                    p.avg_rating as average_rating,
                    p.ratings_count as total_reviews,
                    s.shop_name,
                    s.id as seller_id
                ')
                ->orderByDesc('p.avg_rating')
                ->orderByDesc('p.ratings_count')
                ->first();

            if ($topRatedProduct) {
                return response()->json([
                    'product_id' => $topRatedProduct->product_id,
                    'product_name' => $topRatedProduct->product_name,
                    'price_kg' => $topRatedProduct->price_kg,
                    'price_bunches' => $topRatedProduct->price_bunches,
                    'image_url' => $topRatedProduct->image_url,
                    'average_rating' => round($topRatedProduct->average_rating, 1),
                    'total_reviews' => $topRatedProduct->total_reviews,
                    'shop_name' => $topRatedProduct->shop_name,
                    'seller_id' => $topRatedProduct->seller_id
                ]);
            }

            // Fallback: Return any product with shop name if no rated products
            $anyProduct = DB::table('products as p')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.price_kg,
                    p.price_bunches,
                    p.image_url,
                    s.shop_name,
                    s.id as seller_id
                ')
                ->first();

            if ($anyProduct) {
                return response()->json([
                    'product_id' => $anyProduct->product_id,
                    'product_name' => $anyProduct->product_name,
                    'price_kg' => $anyProduct->price_kg,
                    'price_bunches' => $anyProduct->price_bunches,
                    'image_url' => $anyProduct->image_url,
                    'average_rating' => 0,
                    'total_reviews' => 0,
                    'shop_name' => $anyProduct->shop_name,
                    'seller_id' => $anyProduct->seller_id
                ]);
            }

            return response()->json(['error' => 'No products available'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No rated products available', 'debug' => $e->getMessage()], 200);
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
            $today = now()->format('Y-m-d');
            
            $productSales = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->whereDate('oi.created_at', $today)
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    COALESCE(s.shop_name, s.business_name, "Unknown Seller") as shop_name,
                    s.id as seller_id,
                    u.profile_image as seller_profile_image,
                    COALESCE(p.avg_rating, 0) as avg_rating,
                    COALESCE(p.ratings_count, 0) as ratings_count,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy(
                    'p.product_id',
                    'p.product_name',
                    'p.image_url',
                    's.shop_name',
                    's.business_name',
                    's.id',
                    'u.profile_image',
                    'p.avg_rating',
                    'p.ratings_count'
                )
                ->orderByDesc('total_quantity_sold')
                ->get();

            // Return empty array if no data, not an error object
            return response()->json($productSales->isEmpty() ? [] : $productSales);
        } catch (\Exception $e) {
            // Log error but return empty array instead of error object
            \Log::error('Error fetching daily product sales: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    // 📊 Weekly Product Sales (individual products sold this week)
    public function weeklyProductSales()
    {
        try {
            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();
            
            $productSales = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->whereBetween('oi.created_at', [$weekStart, $weekEnd])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    COALESCE(s.shop_name, s.business_name, "Unknown Seller") as shop_name,
                    s.id as seller_id,
                    u.profile_image as seller_profile_image,
                    COALESCE(p.avg_rating, 0) as avg_rating,
                    COALESCE(p.ratings_count, 0) as ratings_count,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy(
                    'p.product_id',
                    'p.product_name',
                    'p.image_url',
                    's.shop_name',
                    's.business_name',
                    's.id',
                    'u.profile_image',
                    'p.avg_rating',
                    'p.ratings_count'
                )
                ->orderByDesc('total_quantity_sold')
                ->get();

            // Return empty array if no data, not an error object
            return response()->json($productSales->isEmpty() ? [] : $productSales);
        } catch (\Exception $e) {
            // Log error but return empty array instead of error object
            \Log::error('Error fetching weekly product sales: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    // 📊 Monthly Product Sales (individual products sold this month)
    public function monthlyProductSales()
    {
        try {
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();
            
            $productSales = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->whereBetween('oi.created_at', [$monthStart, $monthEnd])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    COALESCE(s.shop_name, s.business_name, "Unknown Seller") as shop_name,
                    s.id as seller_id,
                    u.profile_image as seller_profile_image,
                    COALESCE(p.avg_rating, 0) as avg_rating,
                    COALESCE(p.ratings_count, 0) as ratings_count,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy(
                    'p.product_id',
                    'p.product_name',
                    'p.image_url',
                    's.shop_name',
                    's.business_name',
                    's.id',
                    'u.profile_image',
                    'p.avg_rating',
                    'p.ratings_count'
                )
                ->orderByDesc('total_quantity_sold')
                ->get();

            // Return empty array if no data, not an error object
            return response()->json($productSales->isEmpty() ? [] : $productSales);
        } catch (\Exception $e) {
            // Log error but return empty array instead of error object
            \Log::error('Error fetching monthly product sales: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    // 📊 Yearly Product Sales (individual products sold this year)
    public function yearlyProductSales()
    {
        try {
            $yearStart = now()->startOfYear();
            $yearEnd = now()->endOfYear();
            
            $productSales = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->whereBetween('oi.created_at', [$yearStart, $yearEnd])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    COALESCE(s.shop_name, s.business_name, "Unknown Seller") as shop_name,
                    s.id as seller_id,
                    u.profile_image as seller_profile_image,
                    COALESCE(p.avg_rating, 0) as avg_rating,
                    COALESCE(p.ratings_count, 0) as ratings_count,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy(
                    'p.product_id',
                    'p.product_name',
                    'p.image_url',
                    's.shop_name',
                    's.business_name',
                    's.id',
                    'u.profile_image',
                    'p.avg_rating',
                    'p.ratings_count'
                )
                ->orderByDesc('total_quantity_sold')
                ->get();

            // Return empty array if no data, not an error object
            return response()->json($productSales->isEmpty() ? [] : $productSales);
        } catch (\Exception $e) {
            // Log error but return empty array instead of error object
            \Log::error('Error fetching yearly product sales: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    // 🔍 DEBUG: Check database relationships
    public function debugDatabase()
    {
        try {
            // Check if there are completed orders
            $completedOrders = DB::table('orders')
                ->where('status', 'completed')
                ->count();

            // Check if order_items have seller_id
            $orderItemsWithSeller = DB::table('order_items')
                ->whereNotNull('seller_id')
                ->count();

            // Check if products have seller_id
            $productsWithSeller = DB::table('products')
                ->whereNotNull('seller_id')
                ->count();

            // Check sellers table
            $sellersCount = DB::table('sellers')->count();

            // Check products with ratings
            $productsWithRatings = DB::table('products')
                ->where('ratings_count', '>', 0)
                ->count();

            // Check if order_items can join with products
            $orderItemsWithProducts = DB::table('order_items as oi')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->where('o.status', 'completed')
                ->count();

            // Check total products
            $totalProducts = DB::table('products')->count();

            // Check total order_items
            $totalOrderItems = DB::table('order_items')->count();

            // Check if order_items have product_id
            $orderItemsWithProductId = DB::table('order_items')
                ->whereNotNull('product_id')
                ->count();

            // Test a simple join to see what's happening
            $testJoin = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->where('o.status', 'completed')
                ->selectRaw('oi.product_id, oi.seller_id, oi.quantity, oi.price')
            ->first();

            return response()->json([
                'completed_orders' => $completedOrders,
                'total_products' => $totalProducts,
                'total_order_items' => $totalOrderItems,
                'order_items_with_product_id' => $orderItemsWithProductId,
                'order_items_with_seller_id' => $orderItemsWithSeller,
                'products_with_seller_id' => $productsWithSeller,
                'sellers_count' => $sellersCount,
                'products_with_ratings' => $productsWithRatings,
                'order_items_with_products' => $orderItemsWithProducts,
                'test_join_data' => $testJoin
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Top Products by Sales Amount
     * Returns Top 5 and Top 10 products based on total sales (quantity × price)
     * Supports filtering by time period: daily, weekly, monthly, yearly
     */
    public function topProductsBySales(Request $request)
    {
        try {
            // Get period from request (default to 'monthly')
            $period = $request->input('period', 'monthly');
            
            // Build date filter based on period
            $dateFilter = $this->buildDateFilter($period);
            
            // Query to get top products by total sales amount, including seller & rating info
            $topProducts = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->whereNotNull('p.seller_id') // Only include products with seller_id
                ->where('p.seller_id', '>', 0) // Ensure seller_id is valid
                ->whereBetween('o.created_at', [$dateFilter['startDate'], $dateFilter['endDate']])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    s.business_name,
                    s.id as seller_id,
                    u.profile_image as seller_profile_image,
                    p.avg_rating,
                    p.ratings_count,
                    SUM(COALESCE(oi.actual_weight_kg, oi.estimated_weight_kg, oi.quantity)) as total_quantity_sold,
                    SUM(oi.price * COALESCE(oi.actual_weight_kg, oi.estimated_weight_kg, oi.quantity)) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy(
                    'p.product_id',
                    'p.product_name',
                    'p.image_url',
                    's.shop_name',
                    's.business_name',
                    's.id',
                    'u.profile_image',
                    'p.avg_rating',
                    'p.ratings_count'
                )
                ->orderByDesc('total_sales_amount')
                ->get();

            // Debug: Log raw query results
            \Log::info('Top Products Raw Query Results:', [
                'count' => $topProducts->count(),
                'first_item' => $topProducts->first() ? (array) $topProducts->first() : null
            ]);

            // Format the data - handle nulls like ProductController does
            // Also fetch seller data using Product model relationships if join didn't work
            $formattedProducts = $topProducts->map(function ($product) {
                // Get shop_name with fallback to business_name, similar to ProductController
                $shopName = $product->shop_name ?? $product->business_name ?? null;
                $sellerId = $product->seller_id ?? null;
                $sellerProfileImage = $product->seller_profile_image ?? null;
                
                // If seller data is missing from join, try to fetch it using Product model
                if (!$shopName && $product->product_id) {
                    try {
                        $productModel = \App\Models\Product::with('seller.user')->find($product->product_id);
                        if ($productModel && $productModel->seller) {
                            $shopName = $productModel->seller->shop_name ?? $productModel->seller->business_name ?? null;
                            $sellerId = $productModel->seller->id ?? null;
                            if ($productModel->seller->user) {
                                $sellerProfileImage = $productModel->seller->user->profile_image ?? null;
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to fetch seller data for product ' . $product->product_id . ': ' . $e->getMessage());
                    }
                }
                
                // Ensure all required fields are present, even if null
                return [
                    'product_id' => $product->product_id ?? null,
                    'product_name' => $product->product_name ?? 'Unknown Product',
                    'image_url' => $product->image_url ?? null,
                    'shop_name' => $shopName, // Will be null if both are null
                    'seller_id' => $sellerId,
                    'seller_profile_image' => $sellerProfileImage,
                    'avg_rating' => isset($product->avg_rating) ? (float) $product->avg_rating : 0, // Handle null ratings
                    'ratings_count' => isset($product->ratings_count) ? (int) $product->ratings_count : 0, // Handle null ratings count
                    'total_quantity_sold' => (int) ($product->total_quantity_sold ?? 0),
                    'total_sales_amount' => round((float) ($product->total_sales_amount ?? 0), 2),
                    'total_orders' => (int) ($product->total_orders ?? 0),
                ];
            });

            // Split into Top 5 and Top 10
            $top5 = $formattedProducts->take(5)->values();
            $top10 = $formattedProducts->take(10)->values();

            return response()->json([
                'period' => $period,
                'date_range' => $this->getDateRangeDescription($period),
                'top5' => $top5,
                'top10' => $top10,
                'total_products_found' => $formattedProducts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch top products',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Top Products by Quantity Sold
     * Alternative view focusing on quantity rather than sales amount
     */
    public function topProductsByQuantity(Request $request)
    {
        try {
            $period = $request->input('period', 'monthly');
            $dateFilter = $this->buildDateFilter($period);
            
            $topProducts = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->where('o.status', 'completed')
                ->whereBetween('o.created_at', [$dateFilter['startDate'], $dateFilter['endDate']])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    COALESCE(s.shop_name, s.business_name, "Unknown Seller") as shop_name,
                    s.id as seller_id,
                    u.profile_image as seller_profile_image,
                    COALESCE(p.avg_rating, 0) as avg_rating,
                    COALESCE(p.ratings_count, 0) as ratings_count,
                    SUM(COALESCE(oi.actual_weight_kg, oi.estimated_weight_kg, oi.quantity)) as total_quantity_sold,
                    SUM(oi.price * COALESCE(oi.actual_weight_kg, oi.estimated_weight_kg, oi.quantity)) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy(
                    'p.product_id',
                    'p.product_name',
                    'p.image_url',
                    's.shop_name',
                    's.business_name',
                    's.id',
                    'u.profile_image',
                    'p.avg_rating',
                    'p.ratings_count'
                )
                ->orderByDesc('total_quantity_sold')
                ->get();

            $formattedProducts = $topProducts->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'image_url' => $product->image_url,
                    'shop_name' => $product->shop_name,
                    'seller_id' => $product->seller_id,
                    'seller_profile_image' => $product->seller_profile_image,
                    'avg_rating' => $product->avg_rating,
                    'ratings_count' => $product->ratings_count,
                    'total_quantity_sold' => (int) $product->total_quantity_sold,
                    'total_sales_amount' => round((float) $product->total_sales_amount, 2),
                    'total_orders' => (int) $product->total_orders,
                ];
            });

            $top5 = $formattedProducts->take(5)->values();
            $top10 = $formattedProducts->take(10)->values();

            return response()->json([
                'period' => $period,
                'date_range' => $this->getDateRangeDescription($period),
                'top5' => $top5,
                'top10' => $top10,
                'total_products_found' => $formattedProducts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch top products by quantity',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build date filter based on period
     * Returns proper whereBetween conditions for each time period
     * Uses current time as end date to include all data up to now
     */
    private function buildDateFilter($period)
    {
        $now = now();
        
        switch ($period) {
            case 'daily':
                // Today: Orders from start of today to current time
                $startDate = $now->copy()->startOfDay();
                $endDate = $now; // Use current time instead of end of day
                break;
                
            case 'weekly':
                // This Week: Orders from start of this week (Monday) to current time
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now; // Use current time instead of end of week
                break;
                
            case 'monthly':
                // This Month: Orders from first day of current month to current time
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now; // Use current time instead of end of month
                break;
                
            case 'yearly':
                // This Year: Orders from first day of current year to current time
                $startDate = $now->copy()->startOfYear();
                $endDate = $now; // Use current time instead of end of year
                break;
                
            default:
                // Default to monthly if period is not recognized
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now;
                break;
        }
        
        return [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
    }

    /**
     * Debug date ranges for troubleshooting
     */
    public function debugDateRanges(Request $request)
    {
        $period = $request->input('period', 'monthly');
        $dateFilter = $this->buildDateFilter($period);
        
        // Get some sample order data to see what dates exist
        $sampleOrders = DB::table('orders')
            ->where('status', 'completed')
            ->select('id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        return response()->json([
            'period' => $period,
            'date_filter' => [
                'start_date' => $dateFilter['startDate']->toDateTimeString(),
                'end_date' => $dateFilter['endDate']->toDateTimeString(),
                'start_timestamp' => $dateFilter['startDate']->timestamp,
                'end_timestamp' => $dateFilter['endDate']->timestamp,
            ],
            'sample_orders' => $sampleOrders,
            'current_time' => now()->toDateTimeString(),
            'current_timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Get human-readable date range description
     */
    private function getDateRangeDescription($period)
    {
        $now = now();
        
        switch ($period) {
            case 'daily':
                return $now->format('M d, Y');
                
            case 'weekly':
                return $now->startOfWeek()->format('M d') . ' - ' . $now->endOfWeek()->format('M d, Y');
                
            case 'monthly':
                return $now->format('F Y');
                
            case 'yearly':
                return $now->format('Y');
                
            default:
                return $now->format('F Y');
        }
    }
}
