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
            // Only add columns if they don't exist
            if (!Schema::hasColumn('products', 'available_units')) {
                $table->json('available_units')->nullable()->after('description')->comment('Available units for this product: ["kg", "sack", "tali", "piece", "packet"]');
            }
            
            if (!Schema::hasColumn('products', 'pieces_per_bundle')) {
                $table->integer('pieces_per_bundle')->nullable()->after('available_units')->comment('Number of pieces per tali/bundle (for tali/bundle units)');
            }
        });
        
        // Note: JSON columns cannot be indexed directly in MySQL
        // If indexing is needed, use generated columns instead
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('products', 'available_units')) {
                $table->dropColumn('available_units');
            }
            if (Schema::hasColumn('products', 'pieces_per_bundle')) {
                $table->dropColumn('pieces_per_bundle');
            }
        });
    }
};