<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_schedules', function (Blueprint $table) {
            $table->id();

            // Link to sellers table
            $table->unsignedBigInteger('seller_id');

            // Link to products table (uses product_id instead of id)
            $table->unsignedBigInteger('product_id')->nullable();

            $table->string('crop_name')->nullable();              // e.g., "tomato"
            $table->date('planting_date')->nullable();            // when seeds were planted
            $table->date('expected_harvest_start')->nullable();   // earliest expected harvest
            $table->date('expected_harvest_end')->nullable();     // latest expected harvest
            $table->integer('quantity_estimate')->nullable();     // optional estimated yield
            $table->string('quantity_unit')->nullable();          // e.g., "kg", "bundles"
            $table->boolean('is_active')->default(true);          // only show active schedules
            $table->text('notes')->nullable();                    // optional notes
            $table->timestamps();

            // Foreign keys
            $table->foreign('seller_id')
                  ->references('id')->on('sellers')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('product_id')->on('products')
                  ->onDelete('set null');

            // Indexes for faster lookups
            $table->index('seller_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_schedules');
    }
};
