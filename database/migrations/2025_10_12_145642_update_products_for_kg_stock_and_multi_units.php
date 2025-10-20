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
        
        // Add index only if column exists and index doesn't exist
        if (Schema::hasColumn('products', 'available_units')) {
            Schema::table('products', function (Blueprint $table) {
                // Check if index doesn't exist before adding
                $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('products');
                if (!array_key_exists('products_available_units_index', $indexes)) {
                    $table->index('available_units');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop indexes first if they exist
            if (Schema::hasColumn('products', 'available_units')) {
                $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('products');
                if (array_key_exists('products_available_units_index', $indexes)) {
                    $table->dropIndex(['available_units']);
                }
            }
            
            // Drop columns if they exist
            $columnsToDrop = [];
            if (Schema::hasColumn('products', 'available_units')) {
                $columnsToDrop[] = 'available_units';
            }
            if (Schema::hasColumn('products', 'pieces_per_bundle')) {
                $columnsToDrop[] = 'pieces_per_bundle';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};