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
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('order_items', 'seller_id')) {
                $table->unsignedBigInteger('seller_id')->nullable()->after('product_id');
                $table->foreign('seller_id')->references('id')->on('users')->onDelete('set null');
                $table->index('seller_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'seller_id')) {
                $table->dropForeign(['seller_id']);
                $table->dropIndex(['seller_id']);
                $table->dropColumn('seller_id');
            }
        });
    }
};
