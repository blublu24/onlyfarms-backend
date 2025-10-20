<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeocodingService
{
    /**
     * Get coordinates from address using Google Maps Geocoding API
     * Note: You'll need to add GOOGLE_MAPS_API_KEY to your .env file
     */
    public function getCoordinatesFromAddress(string $address): array
    {
        try {
            $apiKey = config('services.google.maps_api_key');
            
            if (!$apiKey) {
                Log::warning('Google Maps API key not configured, using default coordinates');
                return $this->getDefaultCoordinates($address);
            }

            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
                'region' => 'ph' // Philippines region bias
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    $location = $data['results'][0]['geometry']['location'];
                    
                    Log::info('Geocoding successful', [
                        'address' => $address,
                        'coordinates' => $location
                    ]);
                    
                    return [
                        'lat' => (string) $location['lat'],
                        'lng' => (string) $location['lng']
                    ];
                }
            }

            Log::warning('Geocoding failed, using default coordinates', [
                'address' => $address,
                'response' => $response->body()
            ]);

            return $this->getDefaultCoordinates($address);

        } catch (Exception $e) {
            Log::error('Geocoding exception', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);

            return $this->getDefaultCoordinates($address);
        }
    }

    /**
     * Get default coordinates based on address parsing
     */
    private function getDefaultCoordinates(string $address): array
    {
        // Default coordinates for San Fernando City, Pampanga
        $defaultCoords = [
            'lat' => '15.0300',
            'lng' => '120.6850'
        ];

        // Simple address parsing for common Philippine locations
        $addressLower = strtolower($address);
        
        // Major cities in Pampanga
        $cityCoordinates = [
            'san fernando' => ['lat' => '15.0300', 'lng' => '120.6850'],
            'angeles' => ['lat' => '15.1450', 'lng' => '120.5900'],
            'mabalacat' => ['lat' => '15.2200', 'lng' => '120.5800'],
            'bacolor' => ['lat' => '15.0000', 'lng' => '120.6500'],
            'guagua' => ['lat' => '14.9700', 'lng' => '120.6300'],
            'porac' => ['lat' => '15.0700', 'lng' => '120.5400'],
            'arayat' => ['lat' => '15.1500', 'lng' => '120.7500'],
            'candaba' => ['lat' => '15.1000', 'lng' => '120.8200'],
            'floridablanca' => ['lat' => '14.9800', 'lng' => '120.5200'],
            'lubao' => ['lat' => '14.9300', 'lng' => '120.6000'],
            'macabebe' => ['lat' => '14.9100', 'lng' => '120.7200'],
            'masantol' => ['lat' => '14.9000', 'lng' => '120.7100'],
            'mexico' => ['lat' => '15.0700', 'lng' => '120.7200'],
            'minalin' => ['lat' => '14.9700', 'lng' => '120.6800'],
            'san luis' => ['lat' => '15.0400', 'lng' => '120.7900'],
            'san simon' => ['lat' => '14.9900', 'lng' => '120.7800'],
            'santa ana' => ['lat' => '15.0900', 'lng' => '120.7700'],
            'santa rita' => ['lat' => '14.9900', 'lng' => '120.6100'],
            'santo tomas' => ['lat' => '15.0800', 'lng' => '120.7600'],
            'sasmuan' => ['lat' => '14.9400', 'lng' => '120.6200']
        ];

        // Check if address contains any of the known cities
        foreach ($cityCoordinates as $city => $coords) {
            if (strpos($addressLower, $city) !== false) {
                return [
                    'lat' => (string) $coords['lat'],
                    'lng' => (string) $coords['lng']
                ];
            }
        }

        // If no specific city found, return default San Fernando coordinates
        return $defaultCoords;
    }

    /**
     * Get formatted address from coordinates (reverse geocoding)
     */
    public function getAddressFromCoordinates(float $lat, float $lng): string
    {
        try {
            $apiKey = config('services.google.maps_api_key');
            
            if (!$apiKey) {
                return "Coordinates: {$lat}, {$lng}";
            }

            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $apiKey,
                'region' => 'ph'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    return $data['results'][0]['formatted_address'];
                }
            }

            return "Coordinates: {$lat}, {$lng}";

        } catch (Exception $e) {
            Log::error('Reverse geocoding exception', [
                'coordinates' => "{$lat},{$lng}",
                'error' => $e->getMessage()
            ]);

            return "Coordinates: {$lat}, {$lng}";
        }
    }
}
