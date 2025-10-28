<?php
/**
 * Test Unique Verification Codes
 */

echo "=== Testing Unique Verification Codes ===\n\n";

$apiUrl = 'https://onlyfarms-backend-production.up.railway.app/api/send-email-verification-code';
$testEmails = [
    'test1@example.com',
    'test2@example.com', 
    'test3@example.com',
    'test4@example.com',
    'test5@example.com'
];

$codes = [];

foreach ($testEmails as $email) {
    echo "Testing email: {$email}\n";
    
    $data = ['email' => $email];
    
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        $code = $responseData['code'] ?? $responseData['verification_code'] ?? 'Not provided';
        $codes[] = $code;
        echo "  ✅ Code: {$code}\n";
    } else {
        echo "  ❌ Failed (HTTP {$httpCode})\n";
    }
    
    // Small delay between requests
    sleep(1);
}

echo "\n=== Code Analysis ===\n";
echo "Codes generated: " . implode(', ', $codes) . "\n";

$uniqueCodes = array_unique($codes);
$isUnique = count($uniqueCodes) === count($codes);

if ($isUnique) {
    echo "✅ All codes are UNIQUE! 🎉\n";
} else {
    echo "❌ Some codes are DUPLICATE!\n";
    $duplicates = array_diff_assoc($codes, $uniqueCodes);
    echo "Duplicate codes: " . implode(', ', $duplicates) . "\n";
}

echo "\n=== Test Complete ===\n";
