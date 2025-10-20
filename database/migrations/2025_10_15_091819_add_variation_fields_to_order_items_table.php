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
            $table->string('variation_type')->nullable()->after('image_url'); // premium, typeA, typeB
            $table->string('variation_name')->nullable()->after('variation_type'); // Premium, Type A, Type B
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['variation_type', 'variation_name']);
        });
    }
};
