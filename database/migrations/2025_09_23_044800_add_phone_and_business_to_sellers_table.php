<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Add only the required columns
            if (!Schema::hasColumn('sellers', 'phone_number')) {
                $table->string('phone_number', 50)->nullable();
            }

            if (!Schema::hasColumn('sellers', 'business_permit')) {
                $table->string('business_permit')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Rollback safely
            if (Schema::hasColumn('sellers', 'phone_number')) {
                $table->dropColumn('phone_number');
            }

            if (Schema::hasColumn('sellers', 'business_permit')) {
                $table->dropColumn('business_permit');
            }
        });
    }
};
