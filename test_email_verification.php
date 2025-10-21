<?php
// Simple test script to check email verification endpoint

$url = 'https://onlyfarms-backend-production.up.railway.app/api/send-email-verification-code';
$data = ['email' => 'test@gmail.com'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

echo "Testing endpoint: $url\n";
echo "Payload: " . json_encode($data) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response:\n";
echo $response . "\n";

