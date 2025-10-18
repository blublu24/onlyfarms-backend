<?php

/**
 * Simple Preorder System Test Script
 * 
 * This script tests the basic functionality of the preorder system
 * Run with: php test_preorder_system.php
 */

require_once 'vendor/autoload.php';

use App\Models\Product;
use App\Models\Preorder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\CropSchedule;
use App\Models\UnitConversion;
use Illuminate\Support\Facades\DB;

class PreorderSystemTest
{
    private $testProduct;
    private $testSeller;
    private $testConsumer;
    private $testCropSchedule;
    private $testUnitConversion;

    public function runTests()
    {
        echo "🚀 Starting Preorder System Tests...\n\n";

        try {
            $this->setupTestData();
            $this->testPreorderEligibility();
            $this->testPreorderCreation();
            $this->testPreorderFulfillment();
            $this->testStockManagement();
            $this->cleanupTestData();

            echo "✅ All tests passed successfully!\n";
        } catch (Exception $e) {
            echo "❌ Test failed: " . $e->getMessage() . "\n";
            $this->cleanupTestData();
        }
    }

    private function setupTestData()
    {
        echo "📋 Setting up test data...\n";

        // Create test seller
        $this->testSeller = User::create([
            'name' => 'Test Seller',
            'email' => 'test_seller@example.com',
            'password' => bcrypt('password'),
            'user_type' => 'seller',
        ]);

        // Create test consumer
        $this->testConsumer = User::create([
            'name' => 'Test Consumer',
            'email' => 'test_consumer@example.com',
            'password' => bcrypt('password'),
            'user_type' => 'consumer',
        ]);

        // Create test product with low stock
        $this->testProduct = Product::create([
            'product_name' => 'Test Rice for Preorder',
            'stock_kg' => 1.5, // Low stock for preorder eligibility
            'price_per_kg' => 50.00,
            'premium_stock_kg' => 0.8,
            'premium_price_per_kg' => 75.00,
            'seller_id' => $this->testSeller->id,
        ]);

        // Create crop schedule
        $this->testCropSchedule = CropSchedule::create([
            'product_id' => $this->testProduct->product_id,
            'is_active' => true,
            'expected_harvest_start' => now()->addDays(30),
            'expected_harvest_end' => now()->addDays(45),
        ]);

        // Create unit conversion
        $this->testUnitConversion = UnitConversion::create([
            'product_id' => $this->testProduct->product_id,
            'unit_key' => 'sack',
            'unit_label' => 'Sack',
            'weight_kg' => 25.0,
            'price' => 150.00,
        ]);

        echo "✅ Test data created successfully\n";
    }

    private function testPreorderEligibility()
    {
        echo "\n🔍 Testing preorder eligibility...\n";

        // Test product with low stock should be eligible
        $hasCropSchedule = $this->testProduct->cropSchedules()
            ->where('is_active', true)
            ->exists();

        if (!$hasCropSchedule) {
            throw new Exception("Product should have active crop schedule");
        }

        if ($this->testProduct->stock_kg > 2) {
            throw new Exception("Product should have low stock for preorder eligibility");
        }

        echo "✅ Preorder eligibility test passed\n";
    }

    private function testPreorderCreation()
    {
        echo "\n📝 Testing preorder creation...\n";

        $preorderData = [
            'consumer_id' => $this->testConsumer->id,
            'product_id' => $this->testProduct->product_id,
            'seller_id' => $this->testSeller->id,
            'quantity' => 2,
            'expected_availability_date' => now()->addDays(35)->toDateString(),
            'variation_type' => 'premium',
            'variation_name' => 'Premium',
            'unit_key' => 'sack',
            'unit_price' => 150.00,
            'unit_weight_kg' => 25.0,
            'status' => 'pending',
        ];

        $preorder = Preorder::create($preorderData);

        if (!$preorder) {
            throw new Exception("Failed to create preorder");
        }

        // Verify unit-specific data
        if ($preorder->variation_type !== 'premium') {
            throw new Exception("Variation type not saved correctly");
        }

        if ($preorder->unit_key !== 'sack') {
            throw new Exception("Unit key not saved correctly");
        }

        if ($preorder->unit_price != 150.00) {
            throw new Exception("Unit price not saved correctly");
        }

        echo "✅ Preorder creation test passed\n";
    }

    private function testPreorderFulfillment()
    {
        echo "\n🔄 Testing preorder fulfillment...\n";

        // Get the created preorder
        $preorder = Preorder::where('product_id', $this->testProduct->product_id)
            ->where('consumer_id', $this->testConsumer->id)
            ->first();

        if (!$preorder) {
            throw new Exception("Preorder not found for fulfillment test");
        }

        // Simulate fulfillment process
        DB::beginTransaction();

        try {
            // Update preorder status
            $preorder->updateStatus('fulfilled');

            // Create order from preorder
            $order = Order::create([
                'user_id' => $preorder->consumer_id,
                'total' => $preorder->getSubtotalAttribute(),
                'status' => 'pending',
                'delivery_address' => 'Test Address',
                'note' => 'Order created from preorder #' . $preorder->id,
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'preorder_id' => $preorder->id,
            ]);

            // Create order item
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $preorder->product_id,
                'seller_id' => $preorder->seller_id,
                'product_name' => $preorder->product->product_name,
                'price' => $preorder->unit_price,
                'quantity' => $preorder->quantity,
                'unit' => $preorder->unit_key,
                'variation_type' => $preorder->variation_type,
                'variation_name' => $preorder->variation_name,
                'estimated_weight_kg' => $preorder->getTotalWeightKgAttribute(),
                'estimated_price' => $preorder->getSubtotalAttribute(),
                'reserved' => true,
                'seller_verification_status' => 'pending',
            ]);

            DB::commit();

            // Verify order creation
            if (!$order || !$orderItem) {
                throw new Exception("Failed to create order from preorder");
            }

            echo "✅ Preorder fulfillment test passed\n";

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function testStockManagement()
    {
        echo "\n📦 Testing stock management...\n";

        // Get initial stock
        $initialStock = $this->testProduct->fresh()->premium_stock_kg;

        // Simulate stock reduction after fulfillment
        $preorder = Preorder::where('product_id', $this->testProduct->product_id)
            ->where('consumer_id', $this->testConsumer->id)
            ->first();

        $reservedWeight = $preorder->getTotalWeightKgAttribute();
        $this->testProduct->decrement('premium_stock_kg', $reservedWeight);

        $finalStock = $this->testProduct->fresh()->premium_stock_kg;

        if ($finalStock != ($initialStock - $reservedWeight)) {
            throw new Exception("Stock reduction not calculated correctly");
        }

        echo "✅ Stock management test passed\n";
    }

    private function cleanupTestData()
    {
        echo "\n🧹 Cleaning up test data...\n";

        // Delete in reverse order to avoid foreign key constraints
        OrderItem::where('product_id', $this->testProduct->product_id)->delete();
        Order::where('preorder_id', function($query) {
            $query->select('id')
                  ->from('preorders')
                  ->where('product_id', $this->testProduct->product_id);
        })->delete();
        
        Preorder::where('product_id', $this->testProduct->product_id)->delete();
        UnitConversion::where('product_id', $this->testProduct->product_id)->delete();
        CropSchedule::where('product_id', $this->testProduct->product_id)->delete();
        Product::where('product_id', $this->testProduct->product_id)->delete();
        User::where('id', $this->testSeller->id)->delete();
        User::where('id', $this->testConsumer->id)->delete();

        echo "✅ Test data cleaned up\n";
    }
}

// Run the tests
$test = new PreorderSystemTest();
$test->runTests();
