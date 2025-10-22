<?php
/**
 * Create Admin Account (customizable via CLI args)
 * Usage: php create_admin_custom.php [email] [password] [name]
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

// Read CLI args or use defaults
$email = $argv[1] ?? 'onlyfarms@gmail.com';
$password = $argv[2] ?? 'admin123';
$name = $argv[3] ?? 'OnlyFarms Admin';

try {
    // Check if admin already exists
    $existing = Admin::where('email', $email)->first();
    if ($existing) {
        echo "❌ Admin already exists!\n";
        echo "📧 Email: {$existing->email}\n";
        echo "👤 Name: {$existing->name}\n";
        exit(0);
    }

    // Create admin
    $admin = Admin::create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "✅ Admin created successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📧 Email: {$admin->email}\n";
    echo "🔑 Password: {$password}\n";
    echo "👤 Name: {$admin->name}\n";
    echo "🆔 Admin ID: {$admin->id}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "You can now log in via /api/admin/login with the above credentials.\n";
} catch (\Throwable $e) {
    echo "❌ Error creating admin: {$e->getMessage()}\n";
    exit(1);
}


