<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\LalamoveDelivery;
use App\Services\LalamoveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Mockery;

class LalamoveControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $seller;
    private Product $product;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock configuration values for testing
        config([
            'services.lalamove.api_key' => 'pk_test_test_key',
            'services.lalamove.api_secret' => 'sk_test_test_secret',
            'services.lalamove.base_url' => 'https://rest.sandbox.lalamove.com/v3',
            'services.lalamove.market' => 'PH',
        ]);

        // Create test users manually
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'is_seller' => false,
            'email_verified_at' => now()
        ]);

        $this->seller = User::create([
            'name' => 'Test Seller',
            'email' => 'seller@test.com',
            'password' => bcrypt('password'),
            'is_seller' => true,
            'email_verified_at' => now()
        ]);

        // Create test product manually
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'product_name' => 'Test Product',
            'description' => 'Test Description',
            'price_per_kg' => 100.00,
            'stock_kg' => 10.0,
            'available_units' => ['kg'],
            'status' => 'approved'
        ]);

        // Create test order manually
        $this->order = Order::create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 100.00,
            'delivery_method' => 'delivery',
            'payment_method' => 'cod'
        ]);
    }

    /** @test */
    public function it_can_get_quotation_with_valid_data()
    {
        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('getQuotation')
            ->once()
            ->andReturn([
                'success' => true,
                'quotation_id' => 'test_quotation_123',
                'delivery_fee' => 50.00,
                'expires_at' => '2024-01-01T12:00:00Z',
                'service_type' => 'MOTORCYCLE',
                'distance' => ['value' => '5.2', 'unit' => 'km'],
                'price_breakdown' => ['base' => '45.00', 'total' => '50.00'],
                'stops' => []
            ]);

        $mockService->shouldReceive('createDeliveryRecord')
            ->once()
            ->andReturn(new LalamoveDelivery());

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/lalamove/quotation', [
                'pickup_address' => 'San Fernando City, Pampanga',
                'dropoff_address' => 'Angeles City, Pampanga',
                'service_type' => 'MOTORCYCLE'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'quotation_id' => 'test_quotation_123',
                    'delivery_fee' => 50.00,
                    'service_type' => 'MOTORCYCLE'
                ]
            ]);
    }

    /** @test */
    public function it_validates_quotation_request_data()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/lalamove/quotation', [
                'pickup_address' => '', // Invalid: empty
                'dropoff_address' => 'Angeles City, Pampanga'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_address']);
    }

    /** @test */
    public function it_can_place_order_with_valid_data()
    {
        // Create delivery record first
        $delivery = LalamoveDelivery::create([
            'order_id' => $this->order->id,
            'quotation_id' => 'test_quotation_123',
            'delivery_fee' => 50.00,
            'status' => 'pending',
            'service_type' => 'MOTORCYCLE',
            'pickup_address' => 'San Fernando City, Pampanga',
            'dropoff_address' => 'Angeles City, Pampanga'
        ]);

        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('placeOrder')
            ->once()
            ->andReturn([
                'success' => true,
                'lalamove_order_id' => 'test_order_456',
                'status' => 'ASSIGNING_DRIVER',
                'driver_id' => null,
                'share_link' => 'https://track.lalamove.com/test_order_456',
                'price_breakdown' => ['base' => '45.00', 'total' => '50.00'],
                'distance' => ['value' => '5.2', 'unit' => 'km']
            ]);

        $mockService->shouldReceive('mapLalamoveStatus')
            ->with('ASSIGNING_DRIVER')
            ->andReturn('pending');

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/lalamove/orders', [
                'order_id' => $this->order->id,
                'quotation_id' => 'test_quotation_123',
                'sender_name' => 'Test Seller',
                'sender_phone' => '+639123456789',
                'recipient_name' => 'Test Buyer',
                'recipient_phone' => '+639987654321',
                'recipient_remarks' => 'Please handle with care'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'lalamove_order_id' => 'test_order_456',
                    'status' => 'pending',
                    'share_link' => 'https://track.lalamove.com/test_order_456'
                ]
            ]);

        // Verify delivery record was updated
        $delivery->refresh();
        $this->assertEquals('test_order_456', $delivery->lalamove_order_id);
        $this->assertEquals('pending', $delivery->status);
    }

    /** @test */
    public function it_can_get_order_status()
    {
        // Create delivery record
        $delivery = LalamoveDelivery::create([
            'order_id' => $this->order->id,
            'lalamove_order_id' => 'test_order_456',
            'status' => 'assigned',
            'driver_name' => 'John Driver',
            'driver_phone' => '+639123456789',
            'delivery_fee' => 50.00,
            'service_type' => 'MOTORCYCLE',
            'pickup_address' => 'San Fernando City, Pampanga',
            'dropoff_address' => 'Angeles City, Pampanga'
        ]);

        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('getOrderDetails')
            ->once()
            ->andReturn([
                'success' => true,
                'order_id' => 'test_order_456',
                'status' => 'ON_GOING',
                'driver_id' => 'driver_123',
                'share_link' => 'https://track.lalamove.com/test_order_456',
                'price_breakdown' => ['base' => '45.00', 'total' => '50.00'],
                'distance' => ['value' => '5.2', 'unit' => 'km']
            ]);

        $mockService->shouldReceive('mapLalamoveStatus')
            ->with('ON_GOING')
            ->andReturn('assigned');

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/lalamove/orders/test_order_456');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'lalamove_order_id' => 'test_order_456',
                    'status' => 'assigned',
                    'driver_name' => 'John Driver'
                ]
            ]);
    }

    /** @test */
    public function it_can_cancel_order()
    {
        // Create delivery record
        $delivery = LalamoveDelivery::create([
            'order_id' => $this->order->id,
            'lalamove_order_id' => 'test_order_456',
            'status' => 'assigned',
            'delivery_fee' => 50.00,
            'service_type' => 'MOTORCYCLE',
            'pickup_address' => 'San Fernando City, Pampanga',
            'dropoff_address' => 'Angeles City, Pampanga'
        ]);

        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('cancelOrder')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->actingAs($this->seller, 'sanctum')
            ->deleteJson('/api/lalamove/orders/test_order_456');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);

        // Verify delivery record was updated
        $delivery->refresh();
        $this->assertEquals('cancelled', $delivery->status);
    }

    /** @test */
    public function it_can_add_priority_fee()
    {
        // Create delivery record
        $delivery = LalamoveDelivery::create([
            'order_id' => $this->order->id,
            'lalamove_order_id' => 'test_order_456',
            'status' => 'assigned',
            'delivery_fee' => 50.00,
            'service_type' => 'MOTORCYCLE',
            'pickup_address' => 'San Fernando City, Pampanga',
            'dropoff_address' => 'Angeles City, Pampanga'
        ]);

        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('addPriorityFee')
            ->once()
            ->andReturn([
                'success' => true,
                'order_id' => 'test_order_456',
                'price_breakdown' => ['base' => '45.00', 'total' => '60.00', 'priority_fee' => '10.00']
            ]);

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/lalamove/orders/test_order_456/priority-fee', [
                'amount' => 10.00
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Priority fee added successfully',
                'data' => [
                    'lalamove_order_id' => 'test_order_456',
                    'priority_fee' => 10.00
                ]
            ]);
    }

    /** @test */
    public function it_handles_webhook_with_valid_signature()
    {
        $payload = json_encode([
            'eventType' => 'ORDER_STATUS_CHANGED',
            'orderId' => 'test_order_456',
            'data' => [
                'status' => 'COMPLETED'
            ]
        ]);

        // Create delivery record
        $delivery = LalamoveDelivery::create([
            'order_id' => $this->order->id,
            'lalamove_order_id' => 'test_order_456',
            'status' => 'assigned',
            'delivery_fee' => 50.00,
            'service_type' => 'MOTORCYCLE',
            'pickup_address' => 'San Fernando City, Pampanga',
            'dropoff_address' => 'Angeles City, Pampanga'
        ]);

        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('validateWebhookSignature')
            ->once()
            ->andReturn(true);

        $mockService->shouldReceive('mapLalamoveStatus')
            ->with('COMPLETED')
            ->andReturn('completed');

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->patchJson('/api/lalamove/webhook', [], [
            'X-Request-Signature' => 'valid_signature',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify delivery record was updated
        $delivery->refresh();
        $this->assertEquals('completed', $delivery->status);
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_signature()
    {
        $payload = json_encode([
            'eventType' => 'ORDER_STATUS_CHANGED',
            'orderId' => 'test_order_456',
            'data' => ['status' => 'COMPLETED']
        ]);

        // Mock LalamoveService
        $mockService = Mockery::mock(LalamoveService::class);
        $mockService->shouldReceive('validateWebhookSignature')
            ->once()
            ->andReturn(false);

        $this->app->instance(LalamoveService::class, $mockService);

        $response = $this->patchJson('/api/lalamove/webhook', [], [
            'X-Request-Signature' => 'invalid_signature',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
