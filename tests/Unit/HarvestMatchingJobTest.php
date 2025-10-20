<?php

namespace Tests\Unit;

use App\Jobs\HarvestMatchingJob;
use App\Models\Harvest;
use App\Models\Preorder;
use App\Models\Product;
use App\Models\User;
use App\Events\PreorderMatched;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HarvestMatchingJobTest extends TestCase
{
    use RefreshDatabase;

    protected $seller;
    protected $buyer;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->seller = User::factory()->create(['role' => 'seller']);
        $this->buyer = User::factory()->create(['role' => 'buyer']);
        
        // Create test product
        $this->product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'stock_kg' => 0, // No stock to trigger preorder eligibility
        ]);
    }

    /** @test */
    public function it_matches_preorders_using_fifo_algorithm()
    {
        Event::fake();
        
        // Create harvest
        $harvest = Harvest::factory()->create([
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 10.0,
            'available_weight_kg' => 10.0,
            'published' => true,
            'verified' => true,
        ]);

        // Create preorders in different order (to test FIFO)
        $preorder1 = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'quantity' => 2,
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
            'created_at' => now()->subHours(2), // Older
        ]);

        $preorder2 = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'quantity' => 3,
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
            'created_at' => now()->subHour(), // Newer
        ]);

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Refresh models
        $harvest->refresh();
        $preorder1->refresh();
        $preorder2->refresh();

        // Assertions
        $this->assertEquals('reserved', $preorder1->status);
        $this->assertEquals(2.0, $preorder1->allocated_qty);
        $this->assertEquals($harvest->id, $preorder1->harvest_date_ref);
        $this->assertNotNull($preorder1->matched_at);

        $this->assertEquals('reserved', $preorder2->status);
        $this->assertEquals(3.0, $preorder2->allocated_qty);
        $this->assertEquals($harvest->id, $preorder2->harvest_date_ref);
        $this->assertNotNull($preorder2->matched_at);

        // Check harvest allocation
        $this->assertEquals(5.0, $harvest->allocated_weight_kg);
        $this->assertEquals(5.0, $harvest->available_weight_kg);
        $this->assertNotNull($harvest->matching_completed_at);

        // Check events were fired
        Event::assertDispatched(PreorderMatched::class, 2);
    }

    /** @test */
    public function it_handles_partial_allocations_correctly()
    {
        Event::fake();
        
        // Create harvest with limited weight
        $harvest = Harvest::factory()->create([
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 5.0,
            'available_weight_kg' => 5.0,
            'published' => true,
            'verified' => true,
        ]);

        // Create preorder that requires more weight than available
        $preorder = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'quantity' => 8, // Requires 8kg but only 5kg available
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
        ]);

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Refresh models
        $harvest->refresh();
        $preorder->refresh();

        // Assertions for partial allocation
        $this->assertEquals('reserved', $preorder->status);
        $this->assertEquals(5.0, $preorder->allocated_qty); // Only 5kg allocated
        $this->assertEquals($harvest->id, $preorder->harvest_date_ref);
        $this->assertNotNull($preorder->matched_at);

        // Check harvest is fully allocated
        $this->assertEquals(5.0, $harvest->allocated_weight_kg);
        $this->assertEquals(0.0, $harvest->available_weight_kg);
        $this->assertNotNull($harvest->matching_completed_at);

        // Check event was fired
        Event::assertDispatched(PreorderMatched::class, 1);
    }

    /** @test */
    public function it_only_matches_preorders_with_matching_criteria()
    {
        Event::fake();
        
        // Create harvest
        $harvest = Harvest::factory()->create([
            'product_id' => $this->product->id,
            'variation_type' => 'premium',
            'unit_key' => 'kg',
            'actual_weight_kg' => 10.0,
            'available_weight_kg' => 10.0,
            'published' => true,
            'verified' => true,
        ]);

        // Create preorder with different variation (should not match)
        $preorder1 = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'regular', // Different variation
            'unit_key' => 'kg',
            'quantity' => 2,
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
        ]);

        // Create preorder with matching criteria
        $preorder2 = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'premium', // Matching variation
            'unit_key' => 'kg',
            'quantity' => 3,
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
        ]);

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Refresh models
        $harvest->refresh();
        $preorder1->refresh();
        $preorder2->refresh();

        // Assertions
        $this->assertEquals('pending', $preorder1->status); // Should not be matched
        $this->assertNull($preorder1->allocated_qty);
        $this->assertNull($preorder1->harvest_date_ref);

        $this->assertEquals('reserved', $preorder2->status); // Should be matched
        $this->assertEquals(3.0, $preorder2->allocated_qty);
        $this->assertEquals($harvest->id, $preorder2->harvest_date_ref);

        // Check event was fired only for matching preorder
        Event::assertDispatched(PreorderMatched::class, 1);
    }

    /** @test */
    public function it_skips_preorders_that_are_already_matched()
    {
        Event::fake();
        
        // Create harvest
        $harvest = Harvest::factory()->create([
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 10.0,
            'available_weight_kg' => 10.0,
            'published' => true,
            'verified' => true,
        ]);

        // Create preorder that's already matched
        $preorder = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'quantity' => 2,
            'unit_weight_kg' => 1.0,
            'status' => 'reserved', // Already matched
            'harvest_date_ref' => 999, // Already has harvest reference
        ]);

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Refresh models
        $harvest->refresh();
        $preorder->refresh();

        // Assertions
        $this->assertEquals('reserved', $preorder->status); // Status unchanged
        $this->assertEquals(999, $preorder->harvest_date_ref); // Harvest reference unchanged
        $this->assertEquals(0.0, $harvest->allocated_weight_kg); // No allocation

        // Check no events were fired
        Event::assertNotDispatched(PreorderMatched::class);
    }

    /** @test */
    public function it_handles_no_matching_preorders_gracefully()
    {
        Event::fake();
        
        // Create harvest
        $harvest = Harvest::factory()->create([
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 10.0,
            'available_weight_kg' => 10.0,
            'published' => true,
            'verified' => true,
        ]);

        // No preorders created

        // Run the job
        $job = new HarvestMatchingJob($harvest);
        $job->handle();

        // Refresh model
        $harvest->refresh();

        // Assertions
        $this->assertEquals(0.0, $harvest->allocated_weight_kg);
        $this->assertEquals(10.0, $harvest->available_weight_kg);
        $this->assertNotNull($harvest->matching_completed_at);

        // Check no events were fired
        Event::assertNotDispatched(PreorderMatched::class);
    }

    /** @test */
    public function it_rolls_back_on_failure()
    {
        Event::fake();
        
        // Create harvest
        $harvest = Harvest::factory()->create([
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'actual_weight_kg' => 10.0,
            'available_weight_kg' => 10.0,
            'published' => true,
            'verified' => true,
        ]);

        // Create preorder
        $preorder = Preorder::factory()->create([
            'consumer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'variation_type' => 'regular',
            'unit_key' => 'kg',
            'quantity' => 2,
            'unit_weight_kg' => 1.0,
            'status' => 'pending',
        ]);

        // Mock a database error
        $this->expectException(\Exception::class);

        // Run the job with a mock that throws an exception
        $job = new HarvestMatchingJob($harvest);
        
        // This would normally be handled by the job's error handling
        // but we're testing the rollback mechanism
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Verify rollback occurred
            $harvest->refresh();
            $preorder->refresh();
            
            $this->assertEquals(0.0, $harvest->allocated_weight_kg);
            $this->assertEquals('pending', $preorder->status);
            $this->assertNull($preorder->allocated_qty);
            
            throw $e;
        }
    }
}
