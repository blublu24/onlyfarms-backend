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
        Schema::table('order_items', function (Blueprint $table) {
            // Weight tracking for seller verification (unit already exists)
            $table->decimal('estimated_weight_kg', 10, 4)->after('unit')->comment('Estimated weight in kg based on unit conversion');
            $table->decimal('actual_weight_kg', 10, 4)->nullable()->after('estimated_weight_kg')->comment('Actual weight in kg as confirmed by seller');
            
            // Price tracking
            $table->decimal('price_per_kg_at_order', 8, 2)->after('actual_weight_kg')->comment('Price per kg at time of order');
            $table->decimal('estimated_price', 8, 2)->nullable()->after('price_per_kg_at_order')->comment('Estimated price based on estimated weight');
            
            // Reservation and verification status
            $table->boolean('reserved')->default(true)->after('estimated_price')->comment('Whether this item is reserved in stock');
            $table->enum('seller_verification_status', ['pending', 'seller_accepted', 'seller_rejected', 'seller_adjusted'])->default('pending')->after('reserved');
            
            // Seller notes and confirmation
            $table->text('seller_notes')->nullable()->after('seller_verification_status')->comment('Seller notes during verification');
            $table->timestamp('seller_confirmed_at')->nullable()->after('seller_notes')->comment('When seller confirmed the actual weight');
            
            // Add indexes for performance
            $table->index('reserved');
            $table->index('seller_verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['reserved']);
            $table->dropIndex(['seller_verification_status']);
            
            // Drop columns
            $table->dropColumn([
                'estimated_weight_kg',
                'actual_weight_kg',
                'price_per_kg_at_order',
                'estimated_price',
                'reserved',
                'seller_verification_status',
                'seller_notes',
                'seller_confirmed_at'
            ]);
        });
    }
};