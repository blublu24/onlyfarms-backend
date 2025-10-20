<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add variation price fields
            $table->decimal('premium_price_per_kg', 10, 2)->nullable()->after('price_per_kg');
            $table->decimal('type_a_price_per_kg', 10, 2)->nullable()->after('premium_price_per_kg');
            $table->decimal('type_b_price_per_kg', 10, 2)->nullable()->after('type_a_price_per_kg');
            $table->decimal('type_c_price_per_kg', 10, 2)->nullable()->after('type_b_price_per_kg');
            
            // Add variation stock fields
            $table->decimal('premium_stock_kg', 10, 2)->nullable()->after('type_c_price_per_kg');
            $table->decimal('type_a_stock_kg', 10, 2)->nullable()->after('premium_stock_kg');
            $table->decimal('type_b_stock_kg', 10, 2)->nullable()->after('type_a_stock_kg');
            $table->decimal('type_c_stock_kg', 10, 2)->nullable()->after('type_b_stock_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove variation price and stock fields
            $table->dropColumn([
                'premium_price_per_kg',
                'type_a_price_per_kg', 
                'type_b_price_per_kg',
                'type_c_price_per_kg',
                'premium_stock_kg',
                'type_a_stock_kg',
                'type_b_stock_kg',
                'type_c_stock_kg'
            ]);
        });
    }
};
