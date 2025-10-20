<?php

/**
 * Test sending emails to any Gmail address
 * This demonstrates how your app will send emails to any Gmail user
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "📧 Gmail Email Sending Test\n";
echo "==========================\n\n";

// Get recipient email from command line
$recipientEmail = $argv[1] ?? 'test@gmail.com';

echo "🎯 Testing email sending:\n";
echo "   FROM: jamessbatu@gmail.com (OnlyFarms)\n";
echo "   TO: $recipientEmail (Any Gmail user)\n\n";

// Check configuration
echo "📋 Checking configuration...\n";
echo "   MAIL_USERNAME: " . (env('MAIL_USERNAME') ? '✅ ' . env('MAIL_USERNAME') : '❌ Not set') . "\n";
echo "   MAIL_PASSWORD: " . (env('MAIL_PASSWORD') ? '✅ Set (16 chars)' : '❌ Not set') . "\n";
echo "   MAIL_FROM_ADDRESS: " . (env('MAIL_FROM_ADDRESS') ? '✅ ' . env('MAIL_FROM_ADDRESS') : '❌ Not set') . "\n\n";

if (!env('MAIL_USERNAME') || !env('MAIL_PASSWORD')) {
    echo "❌ Gmail credentials not configured!\n";
    echo "💡 Please set up your Gmail SMTP in .env file first.\n";
    echo "📖 Follow the GMAIL_SENDER_SETUP.md guide.\n";
    exit(1);
}

// Generate verification code
$verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

echo "🔐 Generated verification code: $verificationCode\n";
echo "📤 Sending email...\n\n";

try {
    // Send email from your Gmail to any recipient
    Mail::to($recipientEmail)->send(new VerificationCodeMail($verificationCode));
    
    echo "✅ Email sent successfully!\n";
    echo "📧 Recipient: $recipientEmail\n";
    echo "📱 From: OnlyFarms <jamessbatu@gmail.com>\n";
    echo "📝 Subject: OnlyFarms - Email Verification Code\n";
    echo "🔢 Code: $verificationCode\n\n";
    
    echo "🎉 SUCCESS! Here's what happened:\n";
    echo "   1. Your app used jamessbatu@gmail.com as sender\n";
    echo "   2. Gmail SMTP sent email to $recipientEmail\n";
    echo "   3. Recipient will receive email in their Gmail inbox\n";
    echo "   4. Email contains beautiful HTML template with code\n\n";
    
    echo "📋 Next steps:\n";
    echo "   • Check $recipientEmail inbox\n";
    echo "   • Look for email from OnlyFarms\n";
    echo "   • Check spam folder if not found\n";
    echo "   • Test with different Gmail addresses\n";
    
} catch (Exception $e) {
    echo "❌ Email sending failed: " . $e->getMessage() . "\n\n";
    
    echo "🔧 Common fixes:\n";
    echo "   • Make sure you're using App Password (not regular password)\n";
    echo "   • Enable 2-Factor Authentication on jamessbatu@gmail.com\n";
    echo "   • Check MAIL_USERNAME and MAIL_PASSWORD in .env\n";
    echo "   • Verify Gmail address is correct\n";
    echo "   • Check internet connection\n";
}

echo "\n✨ Test completed!\n";
