<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('receiver_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('product_id')->references('product_id')->on('products')->onDelete('set null');

                // Indexes for better performance
                $table->index(['sender_id', 'receiver_id']);
                $table->index('product_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('conversations');
    }
};
