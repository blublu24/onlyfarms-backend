<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        Admin::updateOrCreate(
            ['email' => 'admin@onlyfarms.com'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@onlyfarms.com',
                'password' => Hash::make('admin1'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@onlyfarms.com');
        $this->command->info('Password: admin1');
    }
}