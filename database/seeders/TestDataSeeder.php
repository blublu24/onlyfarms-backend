<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // Create test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_seller' => true
            ]
        );

        // Create test products
        $products = [
            [
                'product_name' => 'Fresh Eggplant',
                'description' => 'Fresh organic eggplant from local farm',
                'price_per_kg' => 50.00,
                'stock_kg' => 25.5,
                'image_url' => 'products/eggplant.jpg',
                'status' => 'approved',
                'available_units' => ['kg', 'sack', 'small_sack']
            ],
            [
                'product_name' => 'Organic Tomatoes',
                'description' => 'Fresh red tomatoes, perfect for cooking',
                'price_per_kg' => 80.00,
                'stock_kg' => 15.0,
                'image_url' => 'products/tomato.jpg',
                'status' => 'approved',
                'available_units' => ['kg', 'sack', 'small_sack']
            ],
            [
                'product_name' => 'Green Bell Peppers',
                'description' => 'Crisp green bell peppers',
                'price_per_kg' => 120.00,
                'stock_kg' => 8.5,
                'image_url' => 'products/bellpepper.jpg',
                'status' => 'approved',
                'available_units' => ['kg', 'sack', 'small_sack']
            ]
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['product_name' => $productData['product_name']],
                array_merge($productData, ['seller_id' => $user->id])
            );
        }

        echo "Test data created successfully!\n";
    }
}
