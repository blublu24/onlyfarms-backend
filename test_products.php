<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\CropSchedule;

echo "=== PRODUCT DATABASE CHECK ===\n";

// Check total products
$totalProducts = Product::count();
echo "Total Products: $totalProducts\n";

// Check products with low stock
$lowStockProducts = Product::where('stock_kg', '<=', 2)->count();
echo "Products with stock <= 2kg: $lowStockProducts\n";

// Check products with variation stock
$premiumStock = Product::where('premium_stock_kg', '>', 0)->count();
$typeAStock = Product::where('type_a_stock_kg', '>', 0)->count();
$typeBStock = Product::where('type_b_stock_kg', '>', 0)->count();

echo "Products with Premium stock: $premiumStock\n";
echo "Products with Type A stock: $typeAStock\n";
echo "Products with Type B stock: $typeBStock\n";

// Check crop schedules
$cropSchedules = CropSchedule::where('is_active', true)->count();
echo "Active Crop Schedules: $cropSchedules\n";

// Show sample product
$sampleProduct = Product::first();
if ($sampleProduct) {
    echo "\n=== SAMPLE PRODUCT ===\n";
    echo "Name: " . $sampleProduct->product_name . "\n";
    echo "Stock: " . $sampleProduct->stock_kg . "kg\n";
    echo "Premium Stock: " . ($sampleProduct->premium_stock_kg ?? 0) . "kg\n";
    echo "Type A Stock: " . ($sampleProduct->type_a_stock_kg ?? 0) . "kg\n";
    echo "Type B Stock: " . ($sampleProduct->type_b_stock_kg ?? 0) . "kg\n";
    echo "Available Units: " . json_encode($sampleProduct->available_units) . "\n";
    
    // Check if it has crop schedules
    $schedules = $sampleProduct->cropSchedules()->where('is_active', true)->count();
    echo "Active Crop Schedules: $schedules\n";
    
    // Test preorder eligibility
    echo "\n=== TESTING PREORDER ELIGIBILITY ===\n";
    $totalStock = ($sampleProduct->stock_kg ?? 0) + 
                 ($sampleProduct->premium_stock_kg ?? 0) + 
                 ($sampleProduct->type_a_stock_kg ?? 0) + 
                 ($sampleProduct->type_b_stock_kg ?? 0);
    echo "Total Stock: $totalStock kg\n";
    echo "Eligible for preorder: " . ($totalStock <= 2 ? 'YES' : 'NO') . "\n";
} else {
    echo "No products found in database!\n";
}

echo "\n=== DONE ===\n";
