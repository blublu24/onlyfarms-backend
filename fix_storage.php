<?php
/**
 * Railway Storage Fix Script
 * This script ensures the storage symlink is properly created on Railway
 */

echo "🔧 Fixing storage symlink for Railway...\n";

$storagePath = storage_path('app/public');
$publicPath = public_path('storage');

echo "Storage path: $storagePath\n";
echo "Public path: $publicPath\n";

// Check if storage directory exists
if (!is_dir($storagePath)) {
    echo "❌ Storage directory does not exist: $storagePath\n";
    exit(1);
}

// Remove existing symlink if it exists
if (is_link($publicPath)) {
    echo "🗑️ Removing existing symlink...\n";
    unlink($publicPath);
} elseif (is_dir($publicPath)) {
    echo "🗑️ Removing existing directory...\n";
    rmdir($publicPath);
}

// Create the symlink
echo "🔗 Creating storage symlink...\n";
if (symlink($storagePath, $publicPath)) {
    echo "✅ Storage symlink created successfully!\n";
} else {
    echo "❌ Failed to create storage symlink\n";
    exit(1);
}

// Verify the symlink works
if (is_link($publicPath) && is_dir($publicPath)) {
    echo "✅ Storage symlink verified and working!\n";
    
    // List some files to verify
    $files = scandir($publicPath);
    echo "📁 Files in storage: " . implode(', ', array_slice($files, 2, 5)) . "\n";
} else {
    echo "❌ Storage symlink verification failed\n";
    exit(1);
}

echo "🎉 Storage fix completed successfully!\n";
