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
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->whereDate('oi.created_at', $today)
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($productSales);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No daily product sales available', 'debug' => $e->getMessage()], 200);
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
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->whereBetween('oi.created_at', [$weekStart, $weekEnd])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($productSales);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No weekly product sales available', 'debug' => $e->getMessage()], 200);
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
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->whereBetween('oi.created_at', [$monthStart, $monthEnd])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($productSales);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No monthly product sales available', 'debug' => $e->getMessage()], 200);
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
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->whereBetween('oi.created_at', [$yearStart, $yearEnd])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_quantity_sold')
                ->get();

            return response()->json($productSales);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No yearly product sales available', 'debug' => $e->getMessage()], 200);
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
     * Get Top 5 and Top 10 Products by Total Sales
     * Supports time filtering: daily, weekly, monthly, yearly
     */
    public function topProductsBySales(Request $request)
    {
        try {
            $period = $request->input('period', 'monthly'); // Default to monthly
            $dateFilter = $this->getDateFilter($period);
            
            // Base query for product sales data
            $query = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->where($dateFilter['column'], $dateFilter['operator'], $dateFilter['value'])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_sales_amount');

            // Get all results first
            $allProducts = $query->get();
            
            // Extract top 5 and top 10
            $top5 = $allProducts->take(5)->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'image_url' => $product->image_url,
                    'shop_name' => $product->shop_name,
                    'total_quantity_sold' => (int) $product->total_quantity_sold,
                    'total_sales_amount' => round((float) $product->total_sales_amount, 2),
                    'total_orders' => (int) $product->total_orders
                ];
            });

            $top10 = $allProducts->take(10)->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'image_url' => $product->image_url,
                    'shop_name' => $product->shop_name,
                    'total_quantity_sold' => (int) $product->total_quantity_sold,
                    'total_sales_amount' => round((float) $product->total_sales_amount, 2),
                    'total_orders' => (int) $product->total_orders
                ];
            });

            return response()->json([
                'period' => $period,
                'date_range' => $dateFilter['description'],
                'top5' => $top5,
                'top10' => $top10,
                'total_products_found' => $allProducts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch top products',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Top 5 Products by Total Sales (simplified endpoint)
     */
    public function top5Products(Request $request)
    {
        try {
            $period = $request->input('period', 'monthly');
            $dateFilter = $this->getDateFilter($period);
            
            $top5 = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->where($dateFilter['column'], $dateFilter['operator'], $dateFilter['value'])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_sales_amount')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'image_url' => $product->image_url,
                        'shop_name' => $product->shop_name,
                        'total_quantity_sold' => (int) $product->total_quantity_sold,
                        'total_sales_amount' => round((float) $product->total_sales_amount, 2),
                        'total_orders' => (int) $product->total_orders
                    ];
                });

            return response()->json([
                'period' => $period,
                'date_range' => $dateFilter['description'],
                'top5' => $top5
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch top 5 products',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Top 10 Products by Total Sales (simplified endpoint)
     */
    public function top10Products(Request $request)
    {
        try {
            $period = $request->input('period', 'monthly');
            $dateFilter = $this->getDateFilter($period);
            
            $top10 = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.product_id')
                ->join('sellers as s', 'p.seller_id', '=', 's.id')
                ->where('o.status', 'completed')
                ->where($dateFilter['column'], $dateFilter['operator'], $dateFilter['value'])
                ->selectRaw('
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    s.shop_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.price * oi.quantity) as total_sales_amount,
                    COUNT(DISTINCT o.id) as total_orders
                ')
                ->groupBy('p.product_id', 'p.product_name', 'p.image_url', 's.shop_name')
                ->orderByDesc('total_sales_amount')
                ->limit(10)
                ->get()
                ->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'image_url' => $product->image_url,
                        'shop_name' => $product->shop_name,
                        'total_quantity_sold' => (int) $product->total_quantity_sold,
                        'total_sales_amount' => round((float) $product->total_sales_amount, 2),
                        'total_orders' => (int) $product->total_orders
                    ];
                });

            return response()->json([
                'period' => $period,
                'date_range' => $dateFilter['description'],
                'top10' => $top10
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch top 10 products',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get date filter based on period
     */
    private function getDateFilter($period)
    {
        $now = now();
        
        switch ($period) {
            case 'daily':
                return [
                    'column' => DB::raw('DATE(oi.created_at)'),
                    'operator' => '=',
                    'value' => $now->format('Y-m-d'),
                    'description' => 'Today (' . $now->format('M d, Y') . ')'
                ];
                
            case 'weekly':
                $startOfWeek = $now->startOfWeek();
                $endOfWeek = $now->endOfWeek();
                return [
                    'column' => 'oi.created_at',
                    'operator' => 'BETWEEN',
                    'value' => [$startOfWeek, $endOfWeek],
                    'description' => 'This Week (' . $startOfWeek->format('M d') . ' - ' . $endOfWeek->format('M d, Y') . ')'
                ];
                
            case 'monthly':
                $startOfMonth = $now->startOfMonth();
                $endOfMonth = $now->endOfMonth();
                return [
                    'column' => 'oi.created_at',
                    'operator' => 'BETWEEN',
                    'value' => [$startOfMonth, $endOfMonth],
                    'description' => 'This Month (' . $startOfMonth->format('M Y') . ')'
                ];
                
            case 'yearly':
                $startOfYear = $now->startOfYear();
                $endOfYear = $now->endOfYear();
                return [
                    'column' => 'oi.created_at',
                    'operator' => 'BETWEEN',
                    'value' => [$startOfYear, $endOfYear],
                    'description' => 'This Year (' . $startOfYear->format('Y') . ')'
                ];
                
            default:
                // Default to monthly
                $startOfMonth = $now->startOfMonth();
                $endOfMonth = $now->endOfMonth();
                return [
                    'column' => 'oi.created_at',
                    'operator' => 'BETWEEN',
                    'value' => [$startOfMonth, $endOfMonth],
                    'description' => 'This Month (' . $startOfMonth->format('M Y') . ')'
                ];
        }
    }
}
