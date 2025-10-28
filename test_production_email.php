<?php
/**
 * Test Production Email Verification API
 */

echo "=== Testing Production Email Verification API ===\n\n";

$testEmail = 'bluebasco8@gmail.com';
$apiUrl = 'https://onlyfarms-backend-production.up.railway.app/api/send-email-verification-code';

echo "Testing with email: {$testEmail}\n";
echo "API URL: {$apiUrl}\n\n";

// Test data
$data = [
    'email' => $testEmail
];

// Make API request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: {$httpCode}\n";

if ($error) {
    echo "❌ cURL Error: {$error}\n";
} else {
    echo "✅ Response received!\n";
    
    $responseData = json_decode($response, true);
    
    if ($responseData) {
        echo "📧 Message: " . ($responseData['message'] ?? 'No message') . "\n";
        echo "🔑 Verification Code: " . ($responseData['verification_code'] ?? 'Not provided') . "\n";
        echo "📧 Method: " . ($responseData['method'] ?? 'Unknown') . "\n";
        echo "⏰ Expires: " . ($responseData['expires_at'] ?? 'Not provided') . "\n";
        
        if (isset($responseData['gmail_url'])) {
            echo "🔗 Gmail URL: " . $responseData['gmail_url'] . "\n";
        }
        
        if ($httpCode === 200) {
            echo "\n🎉 SUCCESS! Verification code sent to {$testEmail}\n";
            echo "📧 Check the email inbox for the verification code!\n";
        } else {
            echo "\n⚠️ API returned status {$httpCode}\n";
        }
    } else {
        echo "❌ Failed to parse JSON response\n";
        echo "Raw response: {$response}\n";
    }
}

echo "\n=== Test Complete ===\n";
