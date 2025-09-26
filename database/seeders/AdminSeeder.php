<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use DB;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->insert([
            'name' => 'Super Admin',
            'email' => 'admin@onlyfarms.com',
            'password' => Hash::make('anonymous'), // Change to a secure password later
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
