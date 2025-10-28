<?php
/**
 * Fix .env file with real Gmail credentials
 */

$envFile = '.env';
$envContent = file_get_contents($envFile);

// Your real Gmail credentials
$gmailAddress = 'onlyfarms718@gmail.com';
$appPassword = 'emoy oeyt pidz ybsd';

echo "=== Fixing .env file with real Gmail credentials ===\n\n";

// Update the content
$updatedContent = str_replace([
    'MAIL_USERNAME=your-email@gmail.com',
    'MAIL_PASSWORD=your-app-password'
], [
    "MAIL_USERNAME={$gmailAddress}",
    "MAIL_PASSWORD={$appPassword}"
], $envContent);

// Add SMTP credentials if they don't exist
if (strpos($updatedContent, 'SMTP_USERNAME') === false) {
    $updatedContent .= "\n# PHPMailer Configuration\n";
    $updatedContent .= "SMTP_HOST=smtp.gmail.com\n";
    $updatedContent .= "SMTP_PORT=587\n";
    $updatedContent .= "SMTP_USERNAME={$gmailAddress}\n";
    $updatedContent .= "SMTP_PASSWORD={$appPassword}\n";
    $updatedContent .= "SMTP_ENCRYPTION=tls\n";
    $updatedContent .= "SMTP_FROM_ADDRESS={$gmailAddress}\n";
    $updatedContent .= "SMTP_FROM_NAME=\"OnlyFarms\"\n";
} else {
    // Update existing SMTP credentials
    $updatedContent = str_replace([
        'SMTP_USERNAME=your-email@gmail.com',
        'SMTP_PASSWORD=your-app-password'
    ], [
        "SMTP_USERNAME={$gmailAddress}",
        "SMTP_PASSWORD={$appPassword}"
    ], $updatedContent);
}

// Write back to file
file_put_contents($envFile, $updatedContent);

echo "✅ .env file updated successfully!\n";
echo "📧 Gmail Address: {$gmailAddress}\n";
echo "🔑 App Password: " . str_repeat('*', 12) . substr($appPassword, -4) . "\n\n";

echo "Now let's test the email configuration...\n";
echo "Run: php test_real_email.php {$gmailAddress}\n\n";
