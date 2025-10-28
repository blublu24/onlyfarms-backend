<?php
/**
 * Fix .env file - remove spaces from App password
 */

$envFile = '.env';
$envContent = file_get_contents($envFile);

// Your real Gmail credentials (without spaces)
$gmailAddress = 'onlyfarms718@gmail.com';
$appPassword = 'emoyoeytpidzybsd'; // Removed spaces

echo "=== Fixing .env file - removing spaces from App password ===\n\n";

// Update the content with password without spaces
$updatedContent = str_replace([
    'MAIL_USERNAME=onlyfarms718@gmail.com',
    'MAIL_PASSWORD=emoy oeyt pidz ybsd',
    'SMTP_USERNAME=onlyfarms718@gmail.com',
    'SMTP_PASSWORD=emoy oeyt pidz ybsd'
], [
    "MAIL_USERNAME={$gmailAddress}",
    "MAIL_PASSWORD={$appPassword}",
    "SMTP_USERNAME={$gmailAddress}",
    "SMTP_PASSWORD={$appPassword}"
], $envContent);

// Write back to file
file_put_contents($envFile, $updatedContent);

echo "✅ .env file updated successfully!\n";
echo "📧 Gmail Address: {$gmailAddress}\n";
echo "🔑 App Password: " . str_repeat('*', 12) . substr($appPassword, -4) . " (spaces removed)\n\n";

echo "Now let's test the email configuration...\n";
echo "Run: php test_real_email.php {$gmailAddress}\n\n";
