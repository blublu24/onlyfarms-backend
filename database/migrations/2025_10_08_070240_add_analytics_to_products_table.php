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
        Schema::table('products', function (Blueprint $table) {
            // Add analytics fields for search relevance and tracking
            $table->integer('total_sold')->default(0)->after('stocks')->comment('Total quantity sold (completed orders only)');
            $table->decimal('relevance_score', 5, 4)->nullable()->after('total_sold')->comment('Calculated relevance score for search ranking');
            $table->decimal('rating_weight', 5, 4)->nullable()->after('relevance_score')->comment('Weighted rating score (quality + trust)');
            
            // Add indexes for performance
            $table->index('total_sold');
            $table->index('relevance_score');
            $table->index('rating_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['total_sold']);
            $table->dropIndex(['relevance_score']);
            $table->dropIndex(['rating_weight']);
            
            // Drop columns
            $table->dropColumn(['total_sold', 'relevance_score', 'rating_weight']);
        });
    }
};
