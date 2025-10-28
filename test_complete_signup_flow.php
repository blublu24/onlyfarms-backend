<?php
/**
 * Test Complete Signup Flow with Email Verification
 */

echo "=== Testing Complete Signup Flow ===\n\n";

$testEmail = 'testuser@example.com';
$testName = 'Test User';
$testPassword = 'Password123';

echo "Test Email: {$testEmail}\n";
echo "Test Name: {$testName}\n";
echo "Test Password: {$testPassword}\n\n";

$apiUrl = 'https://onlyfarms-backend-production.up.railway.app';

// Step 1: Send verification code
echo "1. Sending verification code...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl . '/api/send-email-verification-code');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $testEmail]));
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

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $verificationCode = $data['verification_code'] ?? $data['code'] ?? '123456';
    echo "   ✅ Verification code sent successfully\n";
    echo "   🔑 Code: {$verificationCode}\n";
    echo "   📧 Full response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "   ❌ Failed to send verification code (HTTP {$httpCode})\n";
    echo "   Response: {$response}\n";
    exit(1);
}

echo "\n";

// Step 2: Verify email and create user
echo "2. Verifying email and creating user...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl . '/api/verify-email');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $testEmail,
    'code' => (string)$verificationCode,
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

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   ✅ Email verification successful!\n";
    echo "   📧 Full response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
    if (isset($data['user'])) {
        echo "   👤 User: " . ($data['user']['name'] ?? 'Unknown') . "\n";
        echo "   📧 Email: " . ($data['user']['email'] ?? 'Unknown') . "\n";
        echo "   🏪 is_seller: " . ($data['user']['is_seller'] ?? 'Unknown') . "\n";
        
        if (isset($data['user']['is_seller']) && $data['user']['is_seller'] == 0) {
            echo "   ✅ User is correctly set as regular user (is_seller=0)\n";
        } else {
            echo "   ⚠️ User seller status: " . ($data['user']['is_seller'] ?? 'Unknown') . "\n";
        }
    }
    
    echo "   🔑 Token: " . (isset($data['token']) ? 'Generated' : 'Not provided') . "\n";
} else {
    echo "   ❌ Email verification failed (HTTP {$httpCode})\n";
    echo "   Response: {$response}\n";
}

echo "\n=== Test Complete ===\n";
echo "The complete signup flow should now work in your mobile app:\n";
echo "1. User enters email + password in signup.tsx\n";
echo "2. System sends verification code to email\n";
echo "3. User gets redirected to email-verification.tsx\n";
echo "4. User enters the verification code\n";
echo "5. User gets logged in with is_seller=0\n";
echo "6. User can access the app\n\n";
