<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add variation breakdown fields to harvests table.
     * This allows farmers to record how much Premium, Type A, and Type B they harvested.
     */
    public function up(): void
    {
        Schema::table('harvests', function (Blueprint $table) {
            // Add variation breakdown fields (in kg)
            $table->decimal('premium_qty_kg', 12, 4)->nullable()->after('yield_qty');
            $table->decimal('type_a_qty_kg', 12, 4)->nullable()->after('premium_qty_kg');
            $table->decimal('type_b_qty_kg', 12, 4)->nullable()->after('type_a_qty_kg');
            
            // Add note about variation breakdown
            $table->text('variation_notes')->nullable()->after('type_b_qty_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvests', function (Blueprint $table) {
            $table->dropColumn(['premium_qty_kg', 'type_a_qty_kg', 'type_b_qty_kg', 'variation_notes']);
        });
    }
};

