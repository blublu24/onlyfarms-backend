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
            // Multi-unit support (stock_kg and price_per_kg already exist)
            $table->json('available_units')->nullable()->after('description')->comment('Available units for this product: ["kg", "sack", "tali", "piece", "packet"]');
            $table->integer('pieces_per_bundle')->nullable()->after('available_units')->comment('Number of pieces per tali/bundle (for tali/bundle units)');
            
            // Add indexes for performance
            $table->index('available_units');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['available_units']);
            
            // Drop columns
            $table->dropColumn([
                'available_units',
                'pieces_per_bundle'
            ]);
        });
    }
};