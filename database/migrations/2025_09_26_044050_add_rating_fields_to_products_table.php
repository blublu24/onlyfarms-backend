<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('avg_rating', 3, 2)->default(0)->after('price'); // e.g. 4.25
            $table->unsignedInteger('ratings_count')->default(0)->after('avg_rating'); // number of ratings
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['avg_rating', 'ratings_count']);
        });
    }
};
