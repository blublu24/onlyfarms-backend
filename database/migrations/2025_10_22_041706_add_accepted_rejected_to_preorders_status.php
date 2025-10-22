<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the status ENUM to include 'accepted' and 'rejected'
        DB::statement("ALTER TABLE preorders MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'reserved', 'ready', 'fulfilled', 'cancelled', 'partially_fulfilled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'accepted' and 'rejected' from the ENUM
        DB::statement("ALTER TABLE preorders MODIFY COLUMN status ENUM('pending', 'reserved', 'ready', 'fulfilled', 'cancelled', 'partially_fulfilled') DEFAULT 'pending'");
    }
};
