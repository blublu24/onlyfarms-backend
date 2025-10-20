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
        Schema::table('sellers', function (Blueprint $table) {
            // Add new required fields for seller registration
            $table->string('email')->nullable()->after('phone_number');
            $table->string('registered_name')->nullable()->after('email');
            $table->string('business_name')->nullable()->after('registered_name');
            $table->string('tin')->nullable()->after('business_name');
            $table->enum('vat_status', ['vat_registered', 'non_vat_registered'])->default('non_vat_registered')->after('tin');
            $table->string('business_email')->nullable()->after('vat_status');
            $table->string('business_phone')->nullable()->after('business_email');
            $table->string('government_id_type')->nullable()->after('business_phone');
            $table->text('government_id_front')->nullable()->after('government_id_type');
            $table->text('government_id_back')->nullable()->after('government_id_front');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('government_id_back');
            
            // Remove old fields that are no longer needed
            if (Schema::hasColumn('sellers', 'business_permit')) {
                $table->dropColumn('business_permit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Remove new fields
            $table->dropColumn([
                'email',
                'registered_name', 
                'business_name',
                'tin',
                'vat_status',
                'business_email',
                'business_phone',
                'government_id_type',
                'government_id_front',
                'government_id_back',
                'status'
            ]);
            
            // Add back old field
            $table->string('business_permit')->nullable();
        });
    }
};