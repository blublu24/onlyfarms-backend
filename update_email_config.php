<?php
/**
 * Update Email Configuration Script
 * This script will help you update your .env file with real Gmail credentials
 */

echo "=== OnlyFarms Email Configuration Setup ===\n\n";

// Read current .env file
$envFile = '.env';
$envContent = file_get_contents($envFile);

echo "Current email configuration:\n";
echo "MAIL_USERNAME=your-email@gmail.com\n";
echo "MAIL_PASSWORD=your-app-password\n\n";

echo "To complete the setup, you need to:\n";
echo "1. Get your Gmail App Password (16 characters)\n";
echo "2. Update the .env file with your real credentials\n\n";

echo "Would you like me to help you update the .env file? (y/n): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
    echo "\nPlease enter your Gmail address: ";
    $handle = fopen("php://stdin", "r");
    $gmailAddress = trim(fgets($handle));
    fclose($handle);
    
    echo "Please enter your Gmail App Password (16 characters): ";
    $handle = fopen("php://stdin", "r");
    $appPassword = trim(fgets($handle));
    fclose($handle);
    
    // Validate inputs
    if (!filter_var($gmailAddress, FILTER_VALIDATE_EMAIL)) {
        echo "❌ Invalid email address format!\n";
        exit(1);
    }
    
    if (strlen($appPassword) !== 16) {
        echo "❌ App password should be 16 characters!\n";
        exit(1);
    }
    
    // Update the .env file
    $updatedContent = str_replace([
        'MAIL_USERNAME=your-email@gmail.com',
        'MAIL_PASSWORD=your-app-password',
        'SMTP_USERNAME=your-email@gmail.com',
        'SMTP_PASSWORD=your-app-password'
    ], [
        "MAIL_USERNAME={$gmailAddress}",
        "MAIL_PASSWORD={$appPassword}",
        "SMTP_USERNAME={$gmailAddress}",
        "SMTP_PASSWORD={$appPassword}"
    ], $envContent);
    
    // Write back to file
    file_put_contents($envFile, $updatedContent);
    
    echo "\n✅ .env file updated successfully!\n";
    echo "📧 Gmail Address: {$gmailAddress}\n";
    echo "🔑 App Password: " . str_repeat('*', 12) . substr($appPassword, -4) . "\n\n";
    
    echo "Now let's test the email configuration...\n";
    echo "Run: php test_real_email.php {$gmailAddress}\n\n";
    
} else {
    echo "\nNo problem! You can manually update the .env file:\n";
    echo "1. Open .env file in a text editor\n";
    echo "2. Replace 'your-email@gmail.com' with your actual Gmail\n";
    echo "3. Replace 'your-app-password' with your 16-character app password\n";
    echo "4. Add these lines if they don't exist:\n";
    echo "   SMTP_USERNAME=your-gmail@gmail.com\n";
    echo "   SMTP_PASSWORD=your-app-password\n\n";
}

echo "=== Setup Complete ===\n";
echo "Next steps:\n";
echo "1. Update .env with your Gmail credentials\n";
echo "2. Run: php test_real_email.php your-email@gmail.com\n";
echo "3. Check your email for test messages\n";
echo "4. If successful, your email verification system is ready! 🎉\n";

