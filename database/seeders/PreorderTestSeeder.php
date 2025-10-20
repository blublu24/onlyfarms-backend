<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use App\Models\CropSchedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PreorderTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Creating test accounts...\n";

        // Delete existing test users if they exist
        User::where('email', 'asherbascoy@gmail.com')->delete();
        User::where('email', 'asherbascog@gmail.com')->delete();

        // Create Seller User
        $sellerUser = User::create([
            'name' => 'Asher Bascoy',
            'email' => 'asherbascoy@gmail.com',
            'password' => Hash::make('Asherbascoy1'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        echo "✅ Seller User created: {$sellerUser->email} (ID: {$sellerUser->id})\n";

        // Create Seller Profile
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'shop_name' => "Bascoy's Farm",
            'business_permit' => '2024-FARM-001',
            'phone_number' => '09123456789',
            'address' => 'Test Farm Address, Philippines',
        ]);

        echo "✅ Seller Profile created (ID: {$seller->id})\n";

        // Create Buyer User
        $buyerUser = User::create([
            'name' => 'Asher Bascog',
            'email' => 'asherbascog@gmail.com',
            'password' => Hash::make('Asherbascog1'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        echo "✅ Buyer User created: {$buyerUser->email} (ID: {$buyerUser->id})\n";

        // Create Test Product with Low Stock
        $product = Product::create([
            'product_name' => 'Fresh Eggplant',
            'description' => 'Organic eggplant perfect for preorder testing',
            'seller_id' => $sellerUser->id,
            'stock_kg' => 1.5,  // Low stock for preorder eligibility
            'price_per_kg' => 50.00,
            'premium_stock_kg' => 0.8,
            'premium_price_per_kg' => 75.00,
            'type_a_stock_kg' => 1.2,
            'type_a_price_per_kg' => 60.00,
            'status' => 'approved',
            'approved_at' => now(),
            'image_url' => 'products/eggplant.jpg', // Make sure this image exists or use default
        ]);

        echo "✅ Product created: {$product->product_name} (ID: {$product->product_id})\n";
        echo "   Stock: {$product->stock_kg}kg (≤2kg = eligible for preorder)\n";

        // Create Crop Schedule
        $cropSchedule = CropSchedule::create([
            'seller_id' => $seller->id,
            'product_id' => $product->product_id,
            'crop_name' => 'Eggplant Batch January 2025',
            'planting_date' => now(),
            'expected_harvest_start' => now()->addDays(30),
            'expected_harvest_end' => now()->addDays(45),
            'quantity_estimate' => 50,
            'quantity_unit' => 'kg',
            'status' => 'Planted',
            'is_active' => true,
        ]);

        echo "✅ Crop Schedule created (ID: {$cropSchedule->id})\n";
        echo "   Harvest Date: {$cropSchedule->expected_harvest_start}\n";

        echo "\n";
        echo "========================================\n";
        echo "✅ TEST DATA CREATED SUCCESSFULLY!\n";
        echo "========================================\n";
        echo "\n";
        echo "SELLER ACCOUNT:\n";
        echo "  Email: asherbascoy@gmail.com\n";
        echo "  Password: Asherbascoy1\n";
        echo "  ID: {$sellerUser->id}\n";
        echo "\n";
        echo "BUYER ACCOUNT:\n";
        echo "  Email: asherbascog@gmail.com\n";
        echo "  Password: Asherbascog1\n";
        echo "  ID: {$buyerUser->id}\n";
        echo "\n";
        echo "TEST PRODUCT:\n";
        echo "  Name: Fresh Eggplant\n";
        echo "  ID: {$product->product_id}\n";
        echo "  Stock: {$product->stock_kg}kg\n";
        echo "  Price: ₱{$product->price_per_kg}/kg\n";
        echo "  Status: Eligible for Preorder ✅\n";
        echo "\n";
        echo "You can now test the preorder system!\n";
        echo "========================================\n";
    }
}

