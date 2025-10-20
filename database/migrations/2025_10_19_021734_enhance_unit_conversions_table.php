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
        Schema::table('unit_conversions', function (Blueprint $table) {
            // Add pricing information for units
            $table->decimal('base_price_per_unit', 10, 2)->nullable()->after('standard_weight_kg'); // Base price for this unit
            $table->decimal('premium_price_per_unit', 10, 2)->nullable()->after('base_price_per_unit'); // Premium variation price
            $table->decimal('type_a_price_per_unit', 10, 2)->nullable()->after('premium_price_per_unit'); // Type A variation price
            $table->decimal('type_b_price_per_unit', 10, 2)->nullable()->after('type_a_price_per_unit'); // Type B variation price
            
            // Add unit metadata
            $table->string('unit_label')->nullable()->after('type_b_price_per_unit'); // Display label (e.g., "Small Sack", "Bundle")
            $table->string('unit_description')->nullable()->after('unit_label'); // Description of the unit
            $table->boolean('is_enabled')->default(true)->after('unit_description'); // Whether this unit is available
            $table->integer('sort_order')->default(0)->after('is_enabled'); // Order for display in UI
            
            // Add indexes for performance
            $table->index(['vegetable_slug', 'is_enabled']);
            $table->index(['unit', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_conversions', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['vegetable_slug', 'is_enabled']);
            $table->dropIndex(['unit', 'is_enabled']);
            
            // Drop columns
            $table->dropColumn([
                'base_price_per_unit',
                'premium_price_per_unit',
                'type_a_price_per_unit',
                'type_b_price_per_unit',
                'unit_label',
                'unit_description',
                'is_enabled',
                'sort_order'
            ]);
        });
    }
};