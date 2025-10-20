<?php

/**
 * Gmail SMTP Test Script
 * Run this to test if Gmail SMTP is working
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Gmail SMTP Configuration\n";
echo "===================================\n\n";

// Get email from command line argument or use default
$testEmail = $argv[1] ?? 'your-test-email@gmail.com';

echo "📧 Testing email to: $testEmail\n\n";

// Test 1: Check mail configuration
echo "1. Checking mail configuration...\n";
$mailConfig = config('mail');
echo "   Mail Driver: " . $mailConfig['default'] . "\n";
echo "   SMTP Host: " . $mailConfig['mailers']['smtp']['host'] . "\n";
echo "   SMTP Port: " . $mailConfig['mailers']['smtp']['port'] . "\n";
echo "   From Address: " . $mailConfig['from']['address'] . "\n";
echo "   From Name: " . $mailConfig['from']['name'] . "\n\n";

// Test 2: Check environment variables
echo "2. Checking environment variables...\n";
echo "   MAIL_USERNAME: " . (env('MAIL_USERNAME') ? '✅ Set (' . env('MAIL_USERNAME') . ')' : '❌ Not set') . "\n";
echo "   MAIL_PASSWORD: " . (env('MAIL_PASSWORD') ? '✅ Set (16 characters)' : '❌ Not set') . "\n";
echo "   MAIL_FROM_ADDRESS: " . (env('MAIL_FROM_ADDRESS') ? '✅ Set (' . env('MAIL_FROM_ADDRESS') . ')' : '❌ Not set') . "\n\n";

// Test 3: Send test email
if (env('MAIL_USERNAME') && env('MAIL_PASSWORD')) {
    echo "3. Sending test email...\n";
    
    $verificationCode = '123456';
    
    try {
        Mail::to($testEmail)->send(new VerificationCodeMail($verificationCode));
        echo "   ✅ Email sent successfully!\n";
        echo "   📧 Check your inbox at: $testEmail\n";
        echo "   🔍 Look for email from: " . env('MAIL_FROM_ADDRESS') . "\n";
        echo "   📱 Subject: OnlyFarms - Email Verification Code\n";
    } catch (Exception $e) {
        echo "   ❌ Email sending failed: " . $e->getMessage() . "\n";
        echo "\n   💡 Common fixes:\n";
        echo "   - Make sure you're using an App Password (not your regular Gmail password)\n";
        echo "   - Enable 2-Factor Authentication on your Gmail account\n";
        echo "   - Check that MAIL_USERNAME and MAIL_PASSWORD are correct in .env\n";
        echo "   - Make sure Gmail address is spelled correctly\n";
    }
} else {
    echo "3. ❌ Cannot test email - credentials not configured\n";
    echo "   💡 Please configure MAIL_USERNAME and MAIL_PASSWORD in .env\n";
}

echo "\n4. Next steps:\n";
echo "   📝 If email failed: Check your .env file configuration\n";
echo "   🔧 If credentials missing: Set up Gmail App Password\n";
echo "   🚀 If email sent: Your Gmail integration is working!\n\n";

echo "✨ Gmail SMTP test completed!\n";
