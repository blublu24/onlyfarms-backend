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
        Schema::table('harvests', function (Blueprint $table) {
            // Add variation information for matching with preorders
            $table->string('variation_type')->nullable()->after('product_id'); // 'premium', 'type_a', 'type_b', 'regular'
            $table->string('variation_name')->nullable()->after('variation_type'); // Display name for the variation
            
            // Add unit information for matching with preorders
            $table->string('unit_key')->nullable()->after('variation_name'); // 'kg', 'sack', 'small_sack', 'tali', 'pieces'
            $table->decimal('actual_weight_kg', 8, 4)->nullable()->after('unit_key'); // Actual weight in kg for allocation
            $table->integer('quantity_units')->nullable()->after('actual_weight_kg'); // Number of units harvested
            
            // Add quality grade field (already exists as 'grade', but ensuring consistency)
            $table->string('quality_grade')->nullable()->after('quantity_units'); // A, B, C, reject
            
            // Add fields for preorder matching tracking
            $table->decimal('allocated_weight_kg', 8, 4)->default(0)->after('quality_grade'); // Weight allocated to preorders
            $table->decimal('available_weight_kg', 8, 4)->nullable()->after('allocated_weight_kg'); // Available weight for allocation
            $table->timestamp('matching_completed_at')->nullable()->after('available_weight_kg'); // When matching job completed
            
            // Add indexes for performance
            $table->index(['variation_type', 'unit_key', 'published']);
            $table->index(['product_id', 'variation_type', 'unit_key']);
            $table->index(['published', 'matching_completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvests', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['variation_type', 'unit_key', 'published']);
            $table->dropIndex(['product_id', 'variation_type', 'unit_key']);
            $table->dropIndex(['published', 'matching_completed_at']);
            
            // Drop columns
            $table->dropColumn([
                'variation_type',
                'variation_name',
                'unit_key',
                'actual_weight_kg',
                'quantity_units',
                'quality_grade',
                'allocated_weight_kg',
                'available_weight_kg',
                'matching_completed_at'
            ]);
        });
    }
};