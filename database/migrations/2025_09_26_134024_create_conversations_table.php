<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // FK to products.product_id (nullable, since chat may not be about a product)
            $table->unsignedBigInteger('product_id')->nullable();

            // FK to users.id (buyer)
            $table->unsignedBigInteger('buyer_id');

            // FK to sellers.id (seller)
            $table->unsignedBigInteger('seller_id');

            $table->timestamp('last_message_at')->nullable(); // for sorting by recent chats
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')
                ->references('product_id')->on('products')
                ->onDelete('set null');

            $table->foreign('buyer_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->foreign('seller_id')
                ->references('id')->on('sellers')
                ->onDelete('cascade');

            // Indexes for faster lookup
            $table->index(['buyer_id', 'seller_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
