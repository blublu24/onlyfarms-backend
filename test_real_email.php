<?php
/**
 * Test Real Email Sending
 * Run this after configuring SMTP credentials
 */

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Real Email Sending ===\n\n";

// Get test email from user
$testEmail = $argv[1] ?? 'test@example.com';
echo "Testing with email: {$testEmail}\n\n";

// Test 1: PHPMailer
echo "1. Testing PHPMailer...\n";
try {
    $phpMailerService = new \App\Services\PhpMailerService();
    
    $htmlBody = "
    <h2>🌱 OnlyFarms Email Test</h2>
    <p>This is a test email to verify your email configuration is working correctly.</p>
    <p><strong>Test Code: 123456</strong></p>
    <p>If you receive this email, your SMTP configuration is working! 🎉</p>
    ";
    
    $textBody = "OnlyFarms Email Test\n\nThis is a test email to verify your email configuration is working correctly.\n\nTest Code: 123456\n\nIf you receive this email, your SMTP configuration is working!";
    
    $phpMailerService->send(
        $testEmail,
        'Test User',
        'OnlyFarms - Email Configuration Test',
        $htmlBody,
        $textBody
    );
    
    echo "   ✅ PHPMailer: Email sent successfully!\n";
    
} catch (Exception $e) {
    echo "   ❌ PHPMailer failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Laravel Mail
echo "2. Testing Laravel Mail...\n";
try {
    \Illuminate\Support\Facades\Mail::to($testEmail)->send(new \App\Mail\VerificationCodeMail('123456'));
    echo "   ✅ Laravel Mail: Email sent successfully!\n";
    
} catch (Exception $e) {
    echo "   ❌ Laravel Mail failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: API Endpoint
echo "3. Testing API Endpoint...\n";
try {
    $response = \Illuminate\Support\Facades\Http::post(env('APP_URL', 'http://localhost:8000') . '/api/send-email-verification-code', [
        'email' => $testEmail
    ]);
    
    $data = $response->json();
    
    if ($response->successful()) {
        echo "   ✅ API Endpoint: Working!\n";
        echo "   📧 Method: " . ($data['method'] ?? 'Unknown') . "\n";
        echo "   🔑 Code: " . ($data['verification_code'] ?? 'Not provided') . "\n";
    } else {
        echo "   ❌ API Endpoint failed: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ API Endpoint error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "Check your email inbox for the test messages!\n";
echo "If you received emails, your configuration is working perfectly! 🎉\n\n";

