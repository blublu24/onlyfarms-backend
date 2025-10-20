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
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('vegetable_slug')->index();
            $table->string('unit'); // 'piece', 'tali', 'packet', 'kg', 'sack', 'small_sack'
            $table->decimal('standard_weight_kg', 10, 4);
            $table->timestamps();
            $table->unique(['vegetable_slug', 'unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
    }
};