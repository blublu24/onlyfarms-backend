<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔐 Testing Lalamove HMAC Signature\n";
echo "==================================\n\n";

$apiKey = config('services.lalamove.api_key');
$apiSecret = config('services.lalamove.api_secret');
echo "API Key: $apiKey\n";
echo "API Secret: " . substr($apiSecret, 0, 20) . "...\n\n";

// Test signature generation
$timestamp = (string) (time() * 1000);
$method = 'GET';
$path = '/v3/cities/PH';
$body = '';

$rawSignature = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
$signature = hash_hmac('sha256', $rawSignature, $apiSecret);
$token = "{$apiKey}:{$timestamp}:{$signature}";

echo "Timestamp: $timestamp\n";
echo "Raw Signature String: " . json_encode($rawSignature) . "\n";
echo "Generated Signature: $signature\n";
echo "Token: $token\n\n";

// Test with a simple API call
echo "Testing simple API call to /v3/cities/PH...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://rest.sandbox.lalamove.com/v3/cities/PH');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: hmac ' . $token,
    'Market: PH',
    'Request-ID: ' . uniqid(),
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    echo "✅ API call successful! Your credentials are working.\n";
} else {
    echo "❌ API call failed. Check your credentials.\n";
}
