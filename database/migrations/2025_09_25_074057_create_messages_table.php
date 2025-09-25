<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('sender_id');
                $table->text('message');
                $table->string('image_url')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
                $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');

                // Indexes for better performance
                $table->index('conversation_id');
                $table->index('sender_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
