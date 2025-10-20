<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\LalamoveDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LalamoveControllerSimpleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_authentication_for_quotation()
    {
        $response = $this->postJson('/api/lalamove/quotation', [
            'pickup_address' => 'San Fernando City, Pampanga',
            'dropoff_address' => 'Angeles City, Pampanga'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_error_for_invalid_quotation_data()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'is_seller' => false,
            'email_verified_at' => now()
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lalamove/quotation', [
                'pickup_address' => '', // Invalid: empty
                'dropoff_address' => 'Angeles City, Pampanga'
            ]);

        // Should return an error (not 200)
        $this->assertNotEquals(200, $response->status());
    }

    /** @test */
    public function it_requires_authentication_for_place_order()
    {
        $response = $this->postJson('/api/lalamove/orders', [
            'order_id' => 1,
            'quotation_id' => 'test_quotation_123'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_get_order_status()
    {
        $response = $this->getJson('/api/lalamove/orders/test_order_456');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_cancel_order()
    {
        $response = $this->deleteJson('/api/lalamove/orders/test_order_456');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_add_priority_fee()
    {
        $response = $this->postJson('/api/lalamove/orders/test_order_456/priority-fee', [
            'amount' => 10.00
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_get_service_types()
    {
        $response = $this->getJson('/api/lalamove/service-types');

        $response->assertStatus(401);
    }

    /** @test */
    public function webhook_route_exists()
    {
        $response = $this->patchJson('/api/lalamove/webhook', [
            'eventType' => 'ORDER_STATUS_CHANGED',
            'orderId' => 'test_order_456'
        ], [
            'X-Request-Signature' => 'test_signature'
        ]);

        // Route exists (not 404)
        $this->assertNotEquals(404, $response->status());
    }
}
