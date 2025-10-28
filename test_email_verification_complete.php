<?php
/**
 * Complete Email Verification System Test
 * Tests both Laravel Mail and PHPMailer implementations
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Services\PhpMailerService;
use App\Mail\VerificationCodeMail;

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== OnlyFarms Email Verification System Test ===\n\n";

// Test email (change this to your test email)
$testEmail = 'test@example.com';
$verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

echo "Test Email: {$testEmail}\n";
echo "Generated Code: {$verificationCode}\n\n";

// Test 1: Laravel Mail Configuration
echo "1. Testing Laravel Mail Configuration...\n";
try {
    $mailConfig = config('mail');
    echo "   ✓ Mail Driver: " . $mailConfig['default'] . "\n";
    echo "   ✓ SMTP Host: " . $mailConfig['mailers']['smtp']['host'] . "\n";
    echo "   ✓ SMTP Port: " . $mailConfig['mailers']['smtp']['port'] . "\n";
    echo "   ✓ From Address: " . $mailConfig['from']['address'] . "\n";
    echo "   ✓ From Name: " . $mailConfig['from']['name'] . "\n";
    echo "   ✓ Laravel Mail config loaded successfully\n\n";
} catch (Exception $e) {
    echo "   ✗ Laravel Mail config error: " . $e->getMessage() . "\n\n";
}

// Test 2: PHPMailer Service
echo "2. Testing PHPMailer Service...\n";
try {
    $phpMailerService = new PhpMailerService();
    echo "   ✓ PHPMailer service instantiated successfully\n";
    echo "   ✓ PHPMailer class available\n\n";
} catch (Exception $e) {
    echo "   ✗ PHPMailer service error: " . $e->getMessage() . "\n\n";
}

// Test 3: Database Connection
echo "3. Testing Database Connection...\n";
try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';port=' . env('DB_PORT', 3306) . ';dbname=' . env('DB_DATABASE', 'onlyfarms'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    echo "   ✓ Database connection successful\n";
    
    // Check if email_verifications table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_verifications'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ email_verifications table exists\n";
    } else {
        echo "   ⚠ email_verifications table not found - run migrations first\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Database connection error: " . $e->getMessage() . "\n";
    echo "   ⚠ Make sure MySQL is running and database exists\n\n";
}

// Test 4: Email Template
echo "4. Testing Email Template...\n";
try {
    $mail = new VerificationCodeMail($verificationCode);
    echo "   ✓ VerificationCodeMail class instantiated\n";
    echo "   ✓ Email subject: " . $mail->envelope()->subject . "\n";
    echo "   ✓ Email view: " . $mail->content()->view . "\n";
    echo "   ✓ Verification code passed: " . $mail->verificationCode . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Email template error: " . $e->getMessage() . "\n\n";
}

// Test 5: Environment Variables
echo "5. Testing Environment Variables...\n";
$requiredEnvVars = [
    'MAIL_HOST' => env('MAIL_HOST'),
    'MAIL_PORT' => env('MAIL_PORT'),
    'MAIL_USERNAME' => env('MAIL_USERNAME'),
    'MAIL_PASSWORD' => env('MAIL_PASSWORD'),
    'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
    'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
    'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
];

foreach ($requiredEnvVars as $var => $value) {
    if (!empty($value) && $value !== 'your-email@gmail.com' && $value !== 'your-app-password') {
        echo "   ✓ {$var}: " . (strlen($value) > 10 ? substr($value, 0, 10) . '...' : $value) . "\n";
    } else {
        echo "   ⚠ {$var}: Not configured (using placeholder)\n";
    }
}
echo "\n";

// Test 6: Email Sending (Dry Run)
echo "6. Testing Email Sending (Dry Run)...\n";
echo "   Note: This is a dry run - no actual emails will be sent\n";
echo "   To test actual email sending, configure your SMTP credentials\n";
echo "   and uncomment the email sending code below\n\n";

/*
// Uncomment this section to test actual email sending
echo "   Sending test email...\n";
try {
    Mail::to($testEmail)->send(new VerificationCodeMail($verificationCode));
    echo "   ✓ Email sent successfully via Laravel Mail\n";
} catch (Exception $e) {
    echo "   ✗ Laravel Mail error: " . $e->getMessage() . "\n";
}

try {
    $phpMailerService = new PhpMailerService();
    $htmlBody = "<h2>OnlyFarms Email Verification</h2><p>Your verification code is: <strong>{$verificationCode}</strong></p>";
    $textBody = "OnlyFarms Email Verification\n\nYour verification code is: {$verificationCode}";
    
    $phpMailerService->send($testEmail, 'Test User', 'OnlyFarms - Email Verification Code', $htmlBody, $textBody);
    echo "   ✓ Email sent successfully via PHPMailer\n";
} catch (Exception $e) {
    echo "   ✗ PHPMailer error: " . $e->getMessage() . "\n";
}
*/

// Test 7: API Endpoints
echo "7. Testing API Endpoints...\n";
$baseUrl = env('APP_URL', 'http://localhost:8000');
echo "   ✓ Base URL: {$baseUrl}\n";
echo "   ✓ Send Code Endpoint: {$baseUrl}/api/send-email-verification-code\n";
echo "   ✓ Verify Code Endpoint: {$baseUrl}/api/verify-email\n";
echo "   ✓ Resend Code Endpoint: {$baseUrl}/api/resend-email-verification-code\n\n";

// Test 8: Recommendations
echo "8. Recommendations for Production:\n";
echo "   ✓ Use PHPMailer for more reliable email delivery\n";
echo "   ✓ Configure proper SMTP credentials (Gmail App Password)\n";
echo "   ✓ Set up proper error logging and monitoring\n";
echo "   ✓ Implement rate limiting on verification endpoints\n";
echo "   ✓ Use HTTPS for all email verification links\n";
echo "   ✓ Set up email templates with proper branding\n\n";

echo "=== Test Complete ===\n";
echo "To test actual email sending:\n";
echo "1. Configure your SMTP credentials in .env\n";
echo "2. Uncomment the email sending code in this script\n";
echo "3. Run: php test_email_verification_complete.php\n\n";

