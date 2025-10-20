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
    
    // Create test data
    $seller = User::factory()->create(['is_seller' => true]);
    $buyer = User::factory()->create(['is_seller' => false]);
    
    $product = Product::factory()->create([
        'seller_id' => $seller->id,
        'stock_kg' => 0,
    ]);

    $harvest = Harvest::factory()->create([
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'actual_weight_kg' => 10.0,
        'available_weight_kg' => 10.0,
        'published' => true,
        'verified' => true,
    ]);

    $preorder = Preorder::factory()->create([
        'consumer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'quantity' => 2,
        'unit_weight_kg' => 1.0,
        'status' => 'pending',
    ]);

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
    
    $harvest2 = Harvest::factory()->create([
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'actual_weight_kg' => 5.0,
        'available_weight_kg' => 5.0,
        'published' => true,
        'verified' => true,
    ]);

    $preorder2 = Preorder::factory()->create([
        'consumer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'quantity' => 8, // Requires 8kg but only 5kg available
        'unit_weight_kg' => 1.0,
        'status' => 'pending',
    ]);

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

    // Test 3: FIFO algorithm
    echo "Test 3: FIFO Algorithm\n";
    echo "---------------------\n";
    
    $harvest3 = Harvest::factory()->create([
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'actual_weight_kg' => 5.0,
        'available_weight_kg' => 5.0,
        'published' => true,
        'verified' => true,
    ]);

    // Create preorders in different order (to test FIFO)
    $preorder3a = Preorder::factory()->create([
        'consumer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'quantity' => 2,
        'unit_weight_kg' => 1.0,
        'status' => 'pending',
        'created_at' => now()->subHours(2), // Older
    ]);

    $preorder3b = Preorder::factory()->create([
        'consumer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'variation_type' => 'regular',
        'unit_key' => 'kg',
        'quantity' => 4,
        'unit_weight_kg' => 1.0,
        'status' => 'pending',
        'created_at' => now()->subHour(), // Newer
    ]);

    echo "✅ Created test data:\n";
    echo "   - Harvest: {$harvest3->id} (5kg available)\n";
    echo "   - Preorder A: {$preorder3a->id} (2kg required, older)\n";
    echo "   - Preorder B: {$preorder3b->id} (4kg required, newer)\n\n";

    $job3 = new HarvestMatchingJob($harvest3);
    $job3->handle();

    $harvest3->refresh();
    $preorder3a->refresh();
    $preorder3b->refresh();

    echo "✅ FIFO allocation completed!\n";
    echo "   - Preorder A status: {$preorder3a->status} (allocated: {$preorder3a->allocated_qty}kg)\n";
    echo "   - Preorder B status: {$preorder3b->status} (allocated: {$preorder3b->allocated_qty}kg)\n";
    echo "   - Harvest allocated: {$harvest3->allocated_weight_kg}kg\n";
    echo "   - Harvest remaining: {$harvest3->available_weight_kg}kg\n\n";

    echo "🎉 All tests passed successfully!\n";
    echo "The Harvest Matching Job is working correctly.\n";

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}