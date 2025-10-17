<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Lalamove API Endpoints\n";
echo "=================================\n\n";

$apiKey = config('services.lalamove.api_key');
$apiSecret = config('services.lalamove.api_secret');
$baseUrl = config('services.lalamove.base_url');

echo "Base URL: $baseUrl\n";
echo "API Key: $apiKey\n";
echo "API Secret: " . substr($apiSecret, 0, 20) . "...\n\n";

// Test 1: Get City Info (like in the tutorial)
echo "1. Testing Get City Info (GET /v3/cities/PH)...\n";

$timestamp = (string) (time() * 1000);
$method = 'GET';
$path = '/v3/cities/PH';
$body = '';

$rawSignature = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
$signature = hash_hmac('sha256', $rawSignature, $apiSecret);
$token = "{$apiKey}:{$timestamp}:{$signature}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/cities/PH');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: hmac ' . $token,
    'Market: PH',
    'Request-ID: ' . uniqid(),
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($error) {
    echo "   cURL Error: $error\n";
}
echo "   Response: " . substr($response, 0, 200) . "...\n\n";

// Test 2: Try a simple quotation request
echo "2. Testing Get Quotation (POST /v3/quotation)...\n";

$timestamp = (string) (time() * 1000);
$method = 'POST';
$path = '/v3/quotation';
$body = json_encode([
    'data' => [
        'serviceType' => 'MOTORCYCLE',
        'language' => 'en_PH',
        'stops' => [
            [
                'coordinates' => ['14.5995', '120.9842'], // Manila coordinates
                'address' => 'Manila, Philippines'
            ],
            [
                'coordinates' => ['14.6760', '121.0437'], // Quezon City coordinates  
                'address' => 'Quezon City, Philippines'
            ]
        ]
    ]
]);

$rawSignature = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
$signature = hash_hmac('sha256', $rawSignature, $apiSecret);
$token = "{$apiKey}:{$timestamp}:{$signature}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/quotation');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: hmac ' . $token,
    'Market: PH',
    'Request-ID: ' . uniqid(),
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($error) {
    echo "   cURL Error: $error\n";
}
echo "   Response: " . substr($response, 0, 300) . "...\n\n";

echo "🏁 Endpoint testing completed!\n";
