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
            // Update status enum to include new statuses from business rules
            $table->dropColumn('status');
            $table->enum('status', ['pending', 'reserved', 'ready', 'fulfilled', 'cancelled', 'partially_fulfilled'])
                  ->default('pending')
                  ->after('unit_price');
            
            // Add harvest_date_ref field (references the harvest that will fulfill this preorder)
            $table->unsignedBigInteger('harvest_date_ref')->nullable()->after('harvest_date');
            $table->foreign('harvest_date_ref')->references('id')->on('harvests')->onDelete('set null');
            
            // Add audit fields for tracking changes
            $table->timestamp('status_updated_at')->nullable()->after('version');
            $table->unsignedBigInteger('status_updated_by')->nullable()->after('status_updated_at');
            $table->foreign('status_updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Add fields for matching and allocation tracking
            $table->decimal('allocated_qty', 8, 4)->nullable()->after('reserved_qty'); // Actual allocated quantity from harvest
            $table->timestamp('matched_at')->nullable()->after('allocated_qty'); // When preorder was matched to harvest
            $table->timestamp('ready_at')->nullable()->after('matched_at'); // When preorder is ready for fulfillment
            
            // Note: Check constraints for non-negative values will be enforced at application level
            
            // Add indexes for performance (as per business rules)
            $table->index(['variation_type', 'unit_key', 'status']);
            $table->index(['harvest_date_ref']);
            $table->index(['matched_at']);
            $table->index(['ready_at']);
            $table->index(['status_updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preorders', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['variation_type', 'unit_key', 'status']);
            $table->dropIndex(['harvest_date_ref']);
            $table->dropIndex(['matched_at']);
            $table->dropIndex(['ready_at']);
            $table->dropIndex(['status_updated_at']);
            
            // Drop foreign keys
            $table->dropForeign(['harvest_date_ref']);
            $table->dropForeign(['status_updated_by']);
            
            // Drop columns
            $table->dropColumn([
                'harvest_date_ref',
                'status_updated_at',
                'status_updated_by',
                'allocated_qty',
                'matched_at',
                'ready_at'
            ]);
            
            // Restore original status enum
            $table->dropColumn('status');
            $table->enum('status', ['pending', 'confirmed', 'fulfilled', 'cancelled'])
                  ->default('pending')
                  ->after('unit_price');
        });
    }
};