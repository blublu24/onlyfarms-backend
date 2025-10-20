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
            // Add the missing columns for multi-unit system
            $table->decimal('stock_kg', 10, 4)->nullable()->after('pieces_per_bundle')->comment('Total stock in kilograms');
            $table->decimal('price_per_kg', 8, 2)->nullable()->after('stock_kg')->comment('Price per kilogram');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_kg', 'price_per_kg']);
        });
    }
};
