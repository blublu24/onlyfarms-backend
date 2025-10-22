<?php
/**
 * Create Admin Account Script
 * Usage: php create_admin_onlyfarms.php
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

try {
    // Check if admin already exists
    $existingAdmin = Admin::where('email', 'onlyfarms@admin.com')->first();
    
    if ($existingAdmin) {
        echo "❌ Admin with email 'onlyfarms@admin.com' already exists!\n";
        echo "Email: " . $existingAdmin->email . "\n";
        echo "Name: " . $existingAdmin->name . "\n";
        exit(1);
    }

    // Create new admin
    $admin = Admin::create([
        'name' => 'OnlyFarms Admin',
        'email' => 'onlyfarms@admin.com',
        'password' => Hash::make('admin1'),
    ]);

    echo "✅ Admin account created successfully!\n";
    echo "📧 Email: " . $admin->email . "\n";
    echo "🔐 Password: admin1\n";
    echo "👤 Name: " . $admin->name . "\n";
    echo "🆔 Admin ID: " . $admin->id . "\n";
    echo "\n✨ You can now log in with:\n";
    echo "  Email: onlyfarms@admin.com\n";
    echo "  Password: admin1\n";

} catch (\Exception $e) {
    echo "❌ Error creating admin account:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
