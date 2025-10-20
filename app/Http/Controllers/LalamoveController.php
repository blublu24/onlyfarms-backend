<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LalamoveController extends Controller
{
    /**
     * Get delivery quotation from Lalamove
     */
    public function getQuotation(Request $request)
    {
        $request->validate([
            'pickup_address' => 'required|string',
            'delivery_address' => 'required|string',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'delivery_lat' => 'required|numeric',
            'delivery_lng' => 'required|numeric',
        ]);

        try {
            // Calculate distance (simple Haversine formula)
            $distance = $this->calculateDistance(
                $request->pickup_lat,
                $request->pickup_lng,
                $request->delivery_lat,
                $request->delivery_lng
            );

            // Base delivery fee calculation (customize as needed)
            $baseFee = 50; // PHP 50 base fee
            $perKmFee = 15; // PHP 15 per km
            
            $deliveryFee = $baseFee + ($distance * $perKmFee);
            
            // Round to 2 decimal places
            $deliveryFee = round($deliveryFee, 2);

            // Estimated delivery time (in minutes)
            $estimatedTime = max(30, ceil($distance * 5)); // 5 minutes per km, minimum 30 min

            Log::info('Lalamove quotation calculated:', [
                'distance_km' => $distance,
                'delivery_fee' => $deliveryFee,
                'estimated_time' => $estimatedTime
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'quotation_id' => 'QUOTE-' . strtoupper(uniqid()),
                    'delivery_fee' => $deliveryFee,
                    'distance_km' => round($distance, 2),
                    'estimated_time_minutes' => $estimatedTime,
                    'pickup_address' => $request->pickup_address,
                    'delivery_address' => $request->delivery_address,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Lalamove quotation error:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get delivery quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Lalamove order status
     */
    public function getOrderStatus($orderId)
    {
        try {
            // In a real implementation, you would call Lalamove API here
            // For now, we'll return mock data
            
            // Mock statuses: PENDING, ASSIGNED, PICKED_UP, IN_TRANSIT, DELIVERED, CANCELLED
            $mockStatuses = [
                'PENDING' => 'Driver is being assigned',
                'ASSIGNED' => 'Driver assigned and on the way to pickup',
                'PICKED_UP' => 'Package picked up by driver',
                'IN_TRANSIT' => 'Package is on the way to delivery address',
                'DELIVERED' => 'Package has been delivered',
                'CANCELLED' => 'Delivery has been cancelled',
            ];

            // For demo purposes, return a random status
            // In production, you'd fetch this from Lalamove API
            $status = 'IN_TRANSIT';
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'status' => $status,
                    'status_description' => $mockStatuses[$status] ?? 'Unknown status',
                    'driver_name' => 'Juan Dela Cruz',
                    'driver_phone' => '+63 912 345 6789',
                    'vehicle_type' => 'Motorcycle',
                    'vehicle_plate' => 'ABC 1234',
                    'estimated_arrival' => now()->addMinutes(15)->toISOString(),
                    'tracking_url' => 'https://www.lalamove.com/track/' . $orderId,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Lalamove order status error:', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get delivery status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in kilometers
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance;
    }
}

