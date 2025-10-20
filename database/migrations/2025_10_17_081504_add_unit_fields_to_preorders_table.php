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
        Schema::table('preorders', function (Blueprint $table) {
            // Variation information
            $table->string('variation_type')->nullable()->after('product_id'); // 'premium', 'type_a', 'type_b', 'regular'
            $table->string('variation_name')->nullable()->after('variation_type'); // Display name for the variation
            
            // Unit information (stored at time of preorder for audit trail)
            $table->string('unit_key')->nullable()->after('variation_name'); // 'kg', 'sack', 'small_sack', 'tali', 'pieces'
            $table->decimal('unit_weight_kg', 8, 4)->nullable()->after('unit_key'); // Weight of the unit at time of preorder
            $table->decimal('unit_price', 10, 2)->nullable()->after('unit_weight_kg'); // Price for that specific unit at time of preorder
            
            // Preorder status and dates
            $table->enum('status', ['pending', 'confirmed', 'fulfilled', 'cancelled'])->default('pending')->after('unit_price');
            $table->date('harvest_date')->nullable()->after('status'); // Actual harvest date when available
            
            // Additional fields
            $table->decimal('reserved_qty', 8, 2)->nullable()->after('harvest_date'); // Reserved quantity in base units (kg)
            $table->text('notes')->nullable()->after('reserved_qty'); // Additional notes from seller or buyer
            $table->integer('version')->default(1)->after('notes'); // For optimistic locking/versioning
            
            // Add indexes for performance
            $table->index(['variation_type', 'status']);
            $table->index(['harvest_date']);
            $table->index(['status', 'harvest_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preorders', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['variation_type', 'status']);
            $table->dropIndex(['harvest_date']);
            $table->dropIndex(['status', 'harvest_date']);
            
            // Drop columns
            $table->dropColumn([
                'variation_type',
                'variation_name',
                'unit_key',
                'unit_weight_kg',
                'unit_price',
                'status',
                'harvest_date',
                'reserved_qty',
                'notes',
                'version'
            ]);
        });
    }
};
