<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // buyer

            // ✅ FIXED: match with addresses.address_id
            $table->unsignedBigInteger('address_id')->nullable();
            $table->foreign('address_id')
                ->references('address_id')->on('addresses')
                ->onDelete('set null');

            $table->decimal('total', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_method')->default('cod');
            $table->text('delivery_address')->nullable(); // snapshot
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
