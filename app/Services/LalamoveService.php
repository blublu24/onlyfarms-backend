<?php

namespace App\Services;

use App\Models\LalamoveDelivery;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LalamoveService
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private string $market;

    public function __construct()
    {
        $this->apiKey = config('services.lalamove.api_key') ?? '';
        $this->apiSecret = config('services.lalamove.api_secret') ?? '';
        $this->baseUrl = config('services.lalamove.base_url') ?? 'https://rest.sandbox.lalamove.com/v3';
        $this->market = config('services.lalamove.market') ?? 'PH';
    }

    /**
     * Generate HMAC SHA256 signature for Lalamove API authentication
     */
    private function generateSignature(string $timestamp, string $method, string $path, string $body = ''): string
    {
        $rawSignature = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
        return hash_hmac('sha256', $rawSignature, $this->apiSecret);
    }

    /**
     * Generate authentication headers for Lalamove API
     */
    private function getAuthHeaders(string $method, string $path, array $body = []): array
    {
        $timestamp = (string) (time() * 1000); // Unix timestamp in milliseconds
        $bodyString = empty($body) ? '' : json_encode($body);
        $signature = $this->generateSignature($timestamp, $method, $path, $bodyString);
        $token = "{$this->apiKey}:{$timestamp}:{$signature}";

        return [
            'Authorization' => "hmac {$token}",
            'Market' => $this->market,
            'Request-ID' => uniqid(),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get coordinates from address using geocoding service
     */
    private function getCoordinatesFromAddress(string $address): array
    {
        $geocodingService = new GeocodingService();
        return $geocodingService->getCoordinatesFromAddress($address);
    }

    /**
     * Get quotation for delivery
     */
    public function getQuotation(string $pickupAddress, string $dropoffAddress, string $serviceType = 'MOTORCYCLE'): array
    {
        try {
            $pickupCoords = $this->getCoordinatesFromAddress($pickupAddress);
            $dropoffCoords = $this->getCoordinatesFromAddress($dropoffAddress);

            $payload = [
                'data' => [
                    'serviceType' => $serviceType,
                    'language' => 'en_PH',
                    'stops' => [
                        [
                            'coordinates' => $pickupCoords,
                            'address' => $pickupAddress
                        ],
                        [
                            'coordinates' => $dropoffCoords,
                            'address' => $dropoffAddress
                        ]
                    ],
                    'isRouteOptimized' => true
                ]
            ];

            $path = '/v3/quotations';
            $headers = $this->getAuthHeaders('POST', $path, $payload);

            Log::info('Lalamove Quotation Request', [
                'pickup_address' => $pickupAddress,
                'dropoff_address' => $dropoffAddress,
                'service_type' => $serviceType
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($this->baseUrl . $path, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Lalamove Quotation Success', $data);

                return [
                    'success' => true,
                    'quotation_id' => $data['data']['quotationId'],
                    'delivery_fee' => (float) $data['data']['priceBreakdown']['total'],
                    'expires_at' => $data['data']['expiresAt'],
                    'service_type' => $data['data']['serviceType'],
                    'distance' => $data['data']['distance'],
                    'price_breakdown' => $data['data']['priceBreakdown'],
                    'stops' => $data['data']['stops']
                ];
            } else {
                Log::error('Lalamove Quotation Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to get quotation: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Quotation Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Place order with Lalamove
     */
    public function placeOrder(string $quotationId, array $senderDetails, array $recipientDetails, bool $isPODEnabled = true): array
    {
        try {
            $payload = [
                'data' => [
                    'quotationId' => $quotationId,
                    'sender' => [
                        'stopId' => $senderDetails['stopId'],
                        'name' => $senderDetails['name'],
                        'phone' => $senderDetails['phone']
                    ],
                    'recipients' => [
                        [
                            'stopId' => $recipientDetails['stopId'],
                            'name' => $recipientDetails['name'],
                            'phone' => $recipientDetails['phone'],
                            'remarks' => $recipientDetails['remarks'] ?? ''
                        ]
                    ],
                    'isPODEnabled' => $isPODEnabled,
                    'metadata' => [
                        'platform' => 'OnlyFarms',
                        'order_type' => 'farm_produce'
                    ]
                ]
            ];

            $path = '/v3/orders';
            $headers = $this->getAuthHeaders('POST', $path, $payload);

            Log::info('Lalamove Place Order Request', [
                'quotation_id' => $quotationId,
                'sender' => $senderDetails['name'],
                'recipient' => $recipientDetails['name']
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($this->baseUrl . $path, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Lalamove Order Placed Successfully', $data);

                return [
                    'success' => true,
                    'lalamove_order_id' => $data['data']['orderId'],
                    'quotation_id' => $data['data']['quotationId'],
                    'status' => $data['data']['status'],
                    'driver_id' => $data['data']['driverId'] ?? null,
                    'share_link' => $data['data']['shareLink'] ?? null,
                    'price_breakdown' => $data['data']['priceBreakdown'] ?? null,
                    'distance' => $data['data']['distance'] ?? null,
                    'stops' => $data['data']['stops'] ?? null
                ];
            } else {
                Log::error('Lalamove Place Order Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to place order: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Place Order Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get order details from Lalamove
     */
    public function getOrderDetails(string $lalamoveOrderId): array
    {
        try {
            $path = "/v3/orders/{$lalamoveOrderId}";
            $headers = $this->getAuthHeaders('GET', $path);

            Log::info('Lalamove Get Order Details Request', [
                'lalamove_order_id' => $lalamoveOrderId
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($this->baseUrl . $path);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Lalamove Order Details Retrieved', $data);

                return [
                    'success' => true,
                    'order_id' => $data['data']['orderId'],
                    'status' => $data['data']['status'],
                    'driver_id' => $data['data']['driverId'] ?? null,
                    'share_link' => $data['data']['shareLink'] ?? null,
                    'price_breakdown' => $data['data']['priceBreakdown'] ?? null,
                    'distance' => $data['data']['distance'] ?? null,
                    'stops' => $data['data']['stops'] ?? null,
                    'metadata' => $data['data']['metadata'] ?? null
                ];
            } else {
                Log::error('Lalamove Get Order Details Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to get order details: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Get Order Details Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get driver details
     */
    public function getDriverDetails(string $lalamoveOrderId, string $driverId): array
    {
        try {
            $path = "/v3/orders/{$lalamoveOrderId}/drivers/{$driverId}";
            $headers = $this->getAuthHeaders('GET', $path);

            Log::info('Lalamove Get Driver Details Request', [
                'lalamove_order_id' => $lalamoveOrderId,
                'driver_id' => $driverId
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($this->baseUrl . $path);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Lalamove Driver Details Retrieved', $data);

                return [
                    'success' => true,
                    'driver_id' => $data['data']['driverId'],
                    'name' => $data['data']['name'],
                    'phone' => $data['data']['phone'],
                    'plate_number' => $data['data']['plateNumber'],
                    'photo' => $data['data']['photo'] ?? null,
                    'coordinates' => $data['data']['coordinates'] ?? null
                ];
            } else {
                Log::error('Lalamove Get Driver Details Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to get driver details: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Get Driver Details Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel order
     */
    public function cancelOrder(string $lalamoveOrderId): array
    {
        try {
            $path = "/v3/orders/{$lalamoveOrderId}";
            $headers = $this->getAuthHeaders('DELETE', $path);

            Log::info('Lalamove Cancel Order Request', [
                'lalamove_order_id' => $lalamoveOrderId
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->delete($this->baseUrl . $path);

            if ($response->successful()) {
                Log::info('Lalamove Order Cancelled Successfully', [
                    'lalamove_order_id' => $lalamoveOrderId
                ]);

                return [
                    'success' => true,
                    'message' => 'Order cancelled successfully'
                ];
            } else {
                Log::error('Lalamove Cancel Order Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to cancel order: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Cancel Order Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add priority fee (tip) to order
     */
    public function addPriorityFee(string $lalamoveOrderId, float $amount): array
    {
        try {
            $payload = [
                'data' => [
                    'priorityFee' => (string) $amount
                ]
            ];

            $path = "/v3/orders/{$lalamoveOrderId}/priority-fee";
            $headers = $this->getAuthHeaders('POST', $path, $payload);

            Log::info('Lalamove Add Priority Fee Request', [
                'lalamove_order_id' => $lalamoveOrderId,
                'amount' => $amount
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($this->baseUrl . $path, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Lalamove Priority Fee Added Successfully', $data);

                return [
                    'success' => true,
                    'order_id' => $data['data']['orderId'],
                    'price_breakdown' => $data['data']['priceBreakdown'] ?? null
                ];
            } else {
                Log::error('Lalamove Add Priority Fee Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to add priority fee: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Add Priority Fee Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get available service types for the market
     */
    public function getCityInfo(): array
    {
        try {
            $path = '/v3/cities';
            $headers = $this->getAuthHeaders('GET', $path);

            Log::info('Lalamove Get City Info Request');

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($this->baseUrl . $path);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Lalamove City Info Retrieved', $data);

                return [
                    'success' => true,
                    'cities' => $data['data'] ?? []
                ];
            } else {
                Log::error('Lalamove Get City Info Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to get city info: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('Lalamove Get City Info Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create or update LalamoveDelivery record
     */
    public function createDeliveryRecord(Order $order, array $quotationData, array $orderData = null): LalamoveDelivery
    {
        $deliveryData = [
            'order_id' => $order->id,
            'quotation_id' => $quotationData['quotation_id'],
            'delivery_fee' => $quotationData['delivery_fee'],
            'service_type' => $quotationData['service_type'],
            'pickup_address' => $quotationData['stops'][0]['address'] ?? '',
            'dropoff_address' => $quotationData['stops'][1]['address'] ?? '',
            'price_breakdown' => $quotationData['price_breakdown'],
            'distance' => $quotationData['distance'],
            'status' => 'pending'
        ];

        if ($orderData) {
            $deliveryData = array_merge($deliveryData, [
                'lalamove_order_id' => $orderData['lalamove_order_id'],
                'driver_id' => $orderData['driver_id'],
                'share_link' => $orderData['share_link'],
                'status' => $this->mapLalamoveStatus($orderData['status'])
            ]);
        }

        return LalamoveDelivery::updateOrCreate(
            ['order_id' => $order->id],
            $deliveryData
        );
    }

    /**
     * Map Lalamove status to our internal status
     */
    private function mapLalamoveStatus(string $lalamoveStatus): string
    {
        return match($lalamoveStatus) {
            'ASSIGNING_DRIVER' => 'pending',
            'ON_GOING' => 'assigned',
            'PICKED_UP' => 'picked_up',
            'COMPLETED' => 'completed',
            'CANCELED' => 'cancelled',
            'REJECTED' => 'cancelled',
            'EXPIRED' => 'cancelled',
            default => 'pending'
        };
    }

    /**
     * Update delivery record with driver details
     */
    public function updateDeliveryWithDriver(LalamoveDelivery $delivery, array $driverData): void
    {
        $delivery->update([
            'driver_name' => $driverData['name'],
            'driver_phone' => $driverData['phone'],
            'plate_number' => $driverData['plate_number']
        ]);
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(string $signature, string $payload): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->apiSecret);
        return hash_equals($expectedSignature, $signature);
    }
}
