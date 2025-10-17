<?php

namespace Tests\Unit;

use App\Services\LalamoveService;
use App\Services\GeocodingService;
use Tests\TestCase;

class LalamoveServiceTest extends TestCase
{
    private LalamoveService $lalamoveService;

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
        
        $this->lalamoveService = new LalamoveService();
    }

    /** @test */
    public function it_can_generate_signature()
    {
        // Test HMAC signature generation
        $reflection = new \ReflectionClass($this->lalamoveService);
        $generateSignatureMethod = $reflection->getMethod('generateSignature');
        $generateSignatureMethod->setAccessible(true);

        $timestamp = '1545880607433';
        $httpMethod = 'POST';
        $path = '/v3/quotations';
        $body = '{"data":{"serviceType":"MOTORCYCLE"}}';

        $signature = $generateSignatureMethod->invoke($this->lalamoveService, $timestamp, $httpMethod, $path, $body);

        $this->assertIsString($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 hex string length
    }

    /** @test */
    public function it_can_get_coordinates_from_address()
    {
        $geocodingService = new GeocodingService();
        
        // Test with San Fernando address
        $coordinates = $geocodingService->getCoordinatesFromAddress('San Fernando City, Pampanga');
        
        $this->assertIsArray($coordinates);
        $this->assertArrayHasKey('lat', $coordinates);
        $this->assertArrayHasKey('lng', $coordinates);
        $this->assertIsString($coordinates['lat']);
        $this->assertIsString($coordinates['lng']);
    }

    /** @test */
    public function it_returns_default_coordinates_for_unknown_address()
    {
        $geocodingService = new GeocodingService();
        
        // Test with unknown address
        $coordinates = $geocodingService->getCoordinatesFromAddress('Unknown City, Unknown Province');
        
        $this->assertIsArray($coordinates);
        $this->assertArrayHasKey('lat', $coordinates);
        $this->assertArrayHasKey('lng', $coordinates);
        
        // Should return San Fernando default coordinates
        $this->assertEquals('15.0300', $coordinates['lat']);
        $this->assertEquals('120.6850', $coordinates['lng']);
    }

    /** @test */
    public function it_can_map_lalamove_status()
    {
        $reflection = new \ReflectionClass($this->lalamoveService);
        $mapStatusMethod = $reflection->getMethod('mapLalamoveStatus');
        $mapStatusMethod->setAccessible(true);

        $this->assertEquals('pending', $mapStatusMethod->invoke($this->lalamoveService, 'ASSIGNING_DRIVER'));
        $this->assertEquals('assigned', $mapStatusMethod->invoke($this->lalamoveService, 'ON_GOING'));
        $this->assertEquals('picked_up', $mapStatusMethod->invoke($this->lalamoveService, 'PICKED_UP'));
        $this->assertEquals('completed', $mapStatusMethod->invoke($this->lalamoveService, 'COMPLETED'));
        $this->assertEquals('cancelled', $mapStatusMethod->invoke($this->lalamoveService, 'CANCELED'));
        $this->assertEquals('cancelled', $mapStatusMethod->invoke($this->lalamoveService, 'REJECTED'));
        $this->assertEquals('cancelled', $mapStatusMethod->invoke($this->lalamoveService, 'EXPIRED'));
    }

    /** @test */
    public function it_validates_webhook_signature()
    {
        $payload = '{"test": "data"}';
        $signature = hash_hmac('sha256', $payload, config('services.lalamove.api_secret'));
        
        $isValid = $this->lalamoveService->validateWebhookSignature($signature, $payload);
        
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_rejects_invalid_webhook_signature()
    {
        $payload = '{"test": "data"}';
        $invalidSignature = 'invalid_signature';
        
        $isValid = $this->lalamoveService->validateWebhookSignature($invalidSignature, $payload);
        
        $this->assertFalse($isValid);
    }
}
