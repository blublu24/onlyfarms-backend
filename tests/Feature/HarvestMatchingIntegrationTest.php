<?php

namespace Tests\Feature;

use App\Jobs\HarvestMatchingJob;
use App\Models\Harvest;
use App\Models\Preorder;
use App\Models\Product;
use App\Models\User;
use App\Events\PreorderMatched;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HarvestMatchingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_match_harvest_to_preorders()
    {
        Event::fake();
        
        // Create test data
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'buyer']);
        
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

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Assertions
        $harvest->refresh();
        $preorder->refresh();

        $this->assertEquals('reserved', $preorder->status);
        $this->assertEquals(2.0, $preorder->allocated_qty);
        $this->assertEquals($harvest->id, $preorder->harvest_date_ref);
        $this->assertNotNull($preorder->matched_at);

        $this->assertEquals(2.0, $harvest->allocated_weight_kg);
        $this->assertEquals(8.0, $harvest->available_weight_kg);
        $this->assertNotNull($harvest->matching_completed_at);

        Event::assertDispatched(PreorderMatched::class, 1);
    }

    /** @test */
    public function it_handles_partial_allocations()
    {
        Event::fake();
        
        // Create test data
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'buyer']);
        
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'stock_kg' => 0,
        ]);

        $harvest = Harvest::factory()->create([
            'product_id' => $product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 5.0,
            'available_weight_kg' => 5.0,
            'published' => true,
            'verified' => true,
        ]);

        $preorder = Preorder::factory()->create([
            'consumer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'quantity' => 8, // Requires 8kg but only 5kg available
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
        ]);

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Assertions
        $harvest->refresh();
        $preorder->refresh();

        $this->assertEquals('reserved', $preorder->status);
        $this->assertEquals(5.0, $preorder->allocated_qty); // Only 5kg allocated
        $this->assertEquals($harvest->id, $preorder->harvest_date_ref);

        $this->assertEquals(5.0, $harvest->allocated_weight_kg);
        $this->assertEquals(0.0, $harvest->available_weight_kg);

        Event::assertDispatched(PreorderMatched::class, 1);
    }

    /** @test */
    public function it_uses_fifo_algorithm()
    {
        Event::fake();
        
        // Create test data
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'buyer']);
        
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'stock_kg' => 0,
        ]);

        $harvest = Harvest::factory()->create([
            'product_id' => $product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 5.0,
            'available_weight_kg' => 5.0,
            'published' => true,
            'verified' => true,
        ]);

        // Create preorders in different order (to test FIFO)
        $preorder1 = Preorder::factory()->create([
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

        $preorder2 = Preorder::factory()->create([
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

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Assertions
        $harvest->refresh();
        $preorder1->refresh();
        $preorder2->refresh();

        // First preorder should be fully allocated (FIFO)
        $this->assertEquals('reserved', $preorder1->status);
        $this->assertEquals(2.0, $preorder1->allocated_qty);

        // Second preorder should be partially allocated
        $this->assertEquals('reserved', $preorder2->status);
        $this->assertEquals(3.0, $preorder2->allocated_qty); // Only 3kg available

        $this->assertEquals(5.0, $harvest->allocated_weight_kg);
        $this->assertEquals(0.0, $harvest->available_weight_kg);

        Event::assertDispatched(PreorderMatched::class, 2);
    }
}
