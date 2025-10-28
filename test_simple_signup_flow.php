<?php
/**
 * Test Simple Signup Flow (Direct Registration)
 */

echo "=== Testing Simple Signup Flow ===\n\n";

$testEmail = 'testuser' . time() . '@example.com';
$testName = 'New User';
$testPhone = '+639' . substr(time(), -9);
$testPassword = 'Password123';

echo "Test Email: {$testEmail}\n";
echo "Test Name: {$testName}\n";
echo "Test Phone: {$testPhone}\n";
echo "Test Password: {$testPassword}\n\n";

$apiUrl = 'https://onlyfarms-backend-production.up.railway.app';

// Register user directly
echo "1. Registering user...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl . '/api/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $testName,
    'email' => $testEmail,
    'phone_number' => $testPhone,
    'password' => $testPassword,
    'password_confirmation' => $testPassword
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 || $httpCode === 201) {
    $data = json_decode($response, true);
    echo "   ✅ User registered successfully!\n";
    echo "   📧 Full response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
    if (isset($data['user'])) {
        echo "   👤 User: " . ($data['user']['name'] ?? 'Unknown') . "\n";
        echo "   📧 Email: " . ($data['user']['email'] ?? 'Unknown') . "\n";
        echo "   📱 Phone: " . ($data['user']['phone_number'] ?? 'Unknown') . "\n";
        echo "   🏪 is_seller: " . ($data['user']['is_seller'] ?? 'Unknown') . "\n";
        
        if (isset($data['user']['is_seller']) && $data['user']['is_seller'] == 0) {
            echo "   ✅ User is correctly set as regular user (is_seller=0)\n";
        } else {
            echo "   ⚠️ User seller status: " . ($data['user']['is_seller'] ?? 'Unknown') . "\n";
        }
    }
    
    echo "   🔑 Token: " . (isset($data['token']) ? 'Generated' : 'Not provided') . "\n";
} else {
    echo "   ❌ User registration failed (HTTP {$httpCode})\n";
    echo "   Response: {$response}\n";
}

echo "\n=== Test Complete ===\n";
echo "The simplified signup flow should now work in your mobile app:\n";
echo "1. User enters email + password in signup.tsx\n";
echo "2. System calls /register to create user account\n";
echo "3. User gets logged in automatically with is_seller=0\n";
echo "4. User can access the app immediately\n\n";
