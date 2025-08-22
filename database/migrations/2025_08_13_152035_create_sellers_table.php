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
        Schema::create('sellers', function (Blueprint $table) {
            $table->id(); // seller_id
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // link to users table
            $table->string('shop_name'); // seller's shop or farm name
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('business_permit')->nullable(); // maybe for verification
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
