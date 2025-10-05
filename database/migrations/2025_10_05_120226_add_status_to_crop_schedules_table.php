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
        Schema::table('crop_schedules', function (Blueprint $table) {
            $table->string('status')->default('Planted')->after('quantity_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crop_schedules', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
