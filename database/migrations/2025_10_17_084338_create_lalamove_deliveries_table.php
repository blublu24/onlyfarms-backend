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
        Schema::create('lalamove_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('quotation_id')->nullable();
            $table->string('lalamove_order_id')->nullable()->unique();
            $table->decimal('delivery_fee', 10, 2);
            $table->string('service_type')->default('MOTORCYCLE');
            $table->text('pickup_address');
            $table->text('dropoff_address');
            $table->string('driver_id')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('status')->default('pending'); // pending, assigned, picked_up, completed, cancelled
            $table->string('share_link')->nullable();
            $table->json('price_breakdown')->nullable();
            $table->json('distance')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lalamove_deliveries');
    }
};
