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
            ['email' => 'superadminonlyfarms@gmail.com'],
            [
                'name' => 'Super Admin',
                'email' => 'superadminonlyfarms@gmail.com',
                'password' => Hash::make('SuperAdmin1'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Name: Super Admin');
        $this->command->info('Email: superadminonlyfarms@gmail.com');
        $this->command->info('Password: SuperAdmin1');
    }
}