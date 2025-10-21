<?php

// One-time script to create admin user on Railway
// Run with: railway run php create_admin.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

try {
    // Check if admin already exists
    $existingAdmin = Admin::where('email', 'superadminonlyfarms@gmail.com')->first();
    
    if ($existingAdmin) {
        echo "❌ Admin already exists!\n";
        echo "Email: superadminonlyfarms@gmail.com\n";
        exit(1);
    }
    
    // Create admin
    $admin = Admin::create([
        'name' => 'Super Admin',
        'email' => 'superadminonlyfarms@gmail.com',
        'password' => Hash::make('SuperAdmin1'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✅ Admin created successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📧 Email: superadminonlyfarms@gmail.com\n";
    echo "🔑 Password: SuperAdmin1\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🚨 Remember to delete this script after use!\n";
    
} catch (\Exception $e) {
    echo "❌ Error creating admin: " . $e->getMessage() . "\n";
    exit(1);
}

