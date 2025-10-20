<?php

/**
 * Simple Email Test Script for OnlyFarms
 * 
 * This script tests the email verification functionality
 * Run this with: php test_email.php
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing OnlyFarms Email Verification System\n";
echo "=============================================\n\n";

// Test 1: Check if mail configuration is loaded
echo "1. Checking mail configuration...\n";
$mailConfig = config('mail');
echo "   Mail Driver: " . $mailConfig['default'] . "\n";
echo "   SMTP Host: " . $mailConfig['mailers']['smtp']['host'] . "\n";
echo "   SMTP Port: " . $mailConfig['mailers']['smtp']['port'] . "\n";
echo "   From Address: " . $mailConfig['from']['address'] . "\n";
echo "   From Name: " . $mailConfig['from']['name'] . "\n\n";

// Test 2: Check environment variables
echo "2. Checking environment variables...\n";
echo "   MAIL_USERNAME: " . (env('MAIL_USERNAME') ? '✅ Set' : '❌ Not set') . "\n";
echo "   MAIL_PASSWORD: " . (env('MAIL_PASSWORD') ? '✅ Set' : '❌ Not set') . "\n";
echo "   MAIL_FROM_ADDRESS: " . (env('MAIL_FROM_ADDRESS') ? '✅ Set' : '❌ Not set') . "\n\n";

// Test 3: Test email sending (if credentials are available)
if (env('MAIL_USERNAME') && env('MAIL_PASSWORD')) {
    echo "3. Testing email sending...\n";
    
    $testEmail = 'test@example.com'; // Change this to your test email
    $verificationCode = '123456';
    
    try {
        // This will only work if you have proper SMTP credentials configured
        Mail::to($testEmail)->send(new VerificationCodeMail($verificationCode));
        echo "   ✅ Email sent successfully!\n";
        echo "   📧 Check your inbox at: $testEmail\n";
    } catch (Exception $e) {
        echo "   ❌ Email sending failed: " . $e->getMessage() . "\n";
        echo "   💡 Make sure to configure your Gmail SMTP settings in .env\n";
    }
} else {
    echo "3. Skipping email test (credentials not configured)\n";
    echo "   💡 Configure MAIL_USERNAME and MAIL_PASSWORD in .env to test\n";
}

echo "\n4. Next steps:\n";
echo "   📝 Follow the GMAIL_SETUP_GUIDE.md to configure Gmail SMTP\n";
echo "   🔧 Update your .env file with Gmail credentials\n";
echo "   🚀 Run the migration: php artisan migrate\n";
echo "   📱 Test the verification flow in your app\n\n";

echo "✨ Email verification system is ready!\n";
