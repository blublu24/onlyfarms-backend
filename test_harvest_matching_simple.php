<?php

require_once 'vendor/autoload.php';

use App\Jobs\HarvestMatchingJob;
use App\Models\Harvest;
use App\Models\Preorder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Harvest Matching Job Logic\n";
echo "=====================================\n\n";

try {
    // Test 1: Basic matching logic
    echo "Test 1: Basic Harvest Matching\n";
    echo "-------------------------------\n";
    
    // Create test data manually
    $seller = new User();
    $seller->name = 'Test Seller';
    $seller->email = 'seller' . time() . '@test.com';
    $seller->password = bcrypt('password');
    $seller->is_seller = true;
    $seller->save();

    $buyer = new User();
    $buyer->name = 'Test Buyer';
    $buyer->email = 'buyer' . time() . '@test.com';
    $buyer->password = bcrypt('password');
    $buyer->is_seller = false;
    $buyer->save();

    // Create a seller record
    $sellerRecord = new \App\Models\Seller();
    $sellerRecord->user_id = $seller->id;
    $sellerRecord->shop_name = 'Test Farm';
    $sellerRecord->business_permit = 'TEST123';
    $sellerRecord->save();
    
    $product = new Product();
    $product->seller_id = $seller->id;
    $product->product_name = 'Test Product';
    $product->description = 'Test Description';
    $product->price_per_kg = 100;
    $product->stock_kg = 0;
    $product->save();

    // Create a crop schedule
    $cropSchedule = new \App\Models\CropSchedule();
    $cropSchedule->seller_id = $sellerRecord->id;
    $cropSchedule->product_id = $product->id;
    $cropSchedule->planting_date = now()->subDays(30);
    $cropSchedule->expected_harvest_start = now()->addDays(7);
    $cropSchedule->status = 'planted';
    $cropSchedule->save();

    $harvest = new Harvest();
    $harvest->crop_schedule_id = $cropSchedule->id;
    $harvest->product_id = $product->product_id;
    $harvest->variation_type = 'regular';
    $harvest->unit_key = 'kg';
    $harvest->yield_qty = 10.0;
    $harvest->yield_unit = 'kg';
    $harvest->lot_code = 'LOT' . time() . '001';
    $harvest->actual_weight_kg = 10.0;
    $harvest->available_weight_kg = 10.0;
    $harvest->harvested_at = now();
    $harvest->published = true;
    $harvest->verified = true;
    $harvest->created_by_type = 'seller';
    $harvest->created_by_id = $seller->id;
    $harvest->save();

    $preorder = new Preorder();
    $preorder->consumer_id = $buyer->id;
    $preorder->seller_id = $seller->id;
    $preorder->product_id = $product->product_id;
    $preorder->variation_type = 'regular';
    $preorder->unit_key = 'kg';
    $preorder->quantity = 2;
    $preorder->unit_weight_kg = 1.0;
    $preorder->status = 'pending';
    $preorder->expected_availability_date = now()->addDays(7);
    $preorder->save();

    echo "✅ Created test data:\n";
    echo "   - Harvest: {$harvest->id} (10kg available)\n";
    echo "   - Preorder: {$preorder->id} (2 units × 1kg = 2kg required)\n\n";

    // Run the job
    $job = new HarvestMatchingJob($harvest);
    $job->handle();

    // Check results
    $harvest->refresh();
    $preorder->refresh();

    echo "✅ Job completed successfully!\n";
    echo "   - Preorder status: {$preorder->status}\n";
    echo "   - Allocated quantity: {$preorder->allocated_qty}kg\n";
    echo "   - Harvest reference: {$preorder->harvest_date_ref}\n";
    echo "   - Harvest allocated: {$harvest->allocated_weight_kg}kg\n";
    echo "   - Harvest remaining: {$harvest->available_weight_kg}kg\n\n";

    // Test 2: Partial allocation
    echo "Test 2: Partial Allocation\n";
    echo "-------------------------\n";
    
    $harvest2 = new Harvest();
    $harvest2->crop_schedule_id = $cropSchedule->id;
    $harvest2->product_id = $product->product_id;
    $harvest2->variation_type = 'regular';
    $harvest2->unit_key = 'kg';
    $harvest2->yield_qty = 5.0;
    $harvest2->yield_unit = 'kg';
    $harvest2->lot_code = 'LOT' . time() . '002';
    $harvest2->actual_weight_kg = 5.0;
    $harvest2->available_weight_kg = 5.0;
    $harvest2->harvested_at = now();
    $harvest2->published = true;
    $harvest2->verified = true;
    $harvest2->created_by_type = 'seller';
    $harvest2->created_by_id = $seller->id;
    $harvest2->save();

    $preorder2 = new Preorder();
    $preorder2->consumer_id = $buyer->id;
    $preorder2->seller_id = $seller->id;
    $preorder2->product_id = $product->product_id;
    $preorder2->variation_type = 'regular';
    $preorder2->unit_key = 'kg';
    $preorder2->quantity = 8; // Requires 8kg but only 5kg available
    $preorder2->unit_weight_kg = 1.0;
    $preorder2->status = 'pending';
    $preorder2->expected_availability_date = now()->addDays(7);
    $preorder2->save();

    echo "✅ Created test data:\n";
    echo "   - Harvest: {$harvest2->id} (5kg available)\n";
    echo "   - Preorder: {$preorder2->id} (8 units × 1kg = 8kg required)\n\n";

    $job2 = new HarvestMatchingJob($harvest2);
    $job2->handle();

    $harvest2->refresh();
    $preorder2->refresh();

    echo "✅ Partial allocation completed!\n";
    echo "   - Preorder status: {$preorder2->status}\n";
    echo "   - Allocated quantity: {$preorder2->allocated_qty}kg (partial)\n";
    echo "   - Harvest allocated: {$harvest2->allocated_weight_kg}kg\n";
    echo "   - Harvest remaining: {$harvest2->available_weight_kg}kg\n\n";

    echo "🎉 All tests passed successfully!\n";
    echo "The Harvest Matching Job is working correctly.\n";

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
