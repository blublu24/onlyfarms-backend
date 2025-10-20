<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if index exists on a table
     */
    private function indexExists($table, $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for preorders table
        Schema::table('preorders', function (Blueprint $table) {
            if (!$this->indexExists('preorders', 'idx_preorders_seller_status_created')) {
                $table->index(['seller_id', 'status', 'created_at'], 'idx_preorders_seller_status_created');
            }
            if (!$this->indexExists('preorders', 'idx_preorders_consumer_status_created')) {
                $table->index(['consumer_id', 'status', 'created_at'], 'idx_preorders_consumer_status_created');
            }
            if (!$this->indexExists('preorders', 'idx_preorders_matching')) {
                $table->index(['variation_type', 'unit_key', 'status', 'created_at'], 'idx_preorders_matching');
            }
        });

        // Add indexes for harvests table
        Schema::table('harvests', function (Blueprint $table) {
            if (!$this->indexExists('harvests', 'idx_harvests_schedule_verified_published')) {
                $table->index(['crop_schedule_id', 'verified', 'published'], 'idx_harvests_schedule_verified_published');
            }
            if (!$this->indexExists('harvests', 'idx_harvests_creator_created')) {
                $table->index(['created_by_type', 'created_by_id', 'created_at'], 'idx_harvests_creator_created');
            }
            if (!$this->indexExists('harvests', 'idx_harvests_harvested_at')) {
                $table->index(['harvested_at'], 'idx_harvests_harvested_at');
            }
            if (!$this->indexExists('harvests', 'idx_harvests_published_at')) {
                $table->index(['published_at'], 'idx_harvests_published_at');
            }
        });

        // Add indexes for unit_conversions table
        Schema::table('unit_conversions', function (Blueprint $table) {
            if (!$this->indexExists('unit_conversions', 'idx_unit_conversions_vegetable_unit_enabled')) {
                $table->index(['vegetable_slug', 'unit', 'is_enabled'], 'idx_unit_conversions_vegetable_unit_enabled');
            }
            if (!$this->indexExists('unit_conversions', 'idx_unit_conversions_vegetable_enabled_sort')) {
                $table->index(['vegetable_slug', 'is_enabled', 'sort_order'], 'idx_unit_conversions_vegetable_enabled_sort');
            }
            if (!$this->indexExists('unit_conversions', 'idx_unit_conversions_vegetable_slug')) {
                $table->index(['vegetable_slug'], 'idx_unit_conversions_vegetable_slug');
            }
            if (!$this->indexExists('unit_conversions', 'idx_unit_conversions_unit')) {
                $table->index(['unit'], 'idx_unit_conversions_unit');
            }
            if (!$this->indexExists('unit_conversions', 'idx_unit_conversions_enabled')) {
                $table->index(['is_enabled'], 'idx_unit_conversions_enabled');
            }
        });

        // Add indexes for products table
        Schema::table('products', function (Blueprint $table) {
            if (!$this->indexExists('products', 'idx_products_seller_status')) {
                $table->index(['seller_id', 'status'], 'idx_products_seller_status');
            }
        });

        // Add indexes for orders table
        Schema::table('orders', function (Blueprint $table) {
            // Check if preorder_id column exists before adding index
            if (Schema::hasColumn('orders', 'preorder_id') && !$this->indexExists('orders', 'idx_orders_preorder_id')) {
                $table->index(['preorder_id'], 'idx_orders_preorder_id');
            }
            if (!$this->indexExists('orders', 'idx_orders_user_status')) {
                $table->index(['user_id', 'status'], 'idx_orders_user_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop preorders indexes
        Schema::table('preorders', function (Blueprint $table) {
            $table->dropIndex('idx_preorders_product_variation_status');
            $table->dropIndex('idx_preorders_seller_status_created');
            $table->dropIndex('idx_preorders_consumer_status_created');
            $table->dropIndex('idx_preorders_harvest_status');
            $table->dropIndex('idx_preorders_matching');
            $table->dropIndex('idx_preorders_status');
            $table->dropIndex('idx_preorders_unit_key');
            $table->dropIndex('idx_preorders_variation_type');
            $table->dropIndex('idx_preorders_harvest_date');
            $table->dropIndex('idx_preorders_matched_at');
            $table->dropIndex('idx_preorders_ready_at');
        });

        // Drop harvests indexes
        Schema::table('harvests', function (Blueprint $table) {
            $table->dropIndex('idx_harvests_product_variation_unit_published');
            $table->dropIndex('idx_harvests_schedule_verified_published');
            $table->dropIndex('idx_harvests_matching_candidates');
            $table->dropIndex('idx_harvests_creator_created');
            $table->dropIndex('idx_harvests_published');
            $table->dropIndex('idx_harvests_verified');
            $table->dropIndex('idx_harvests_variation_type');
            $table->dropIndex('idx_harvests_unit_key');
            $table->dropIndex('idx_harvests_harvested_at');
            $table->dropIndex('idx_harvests_published_at');
            $table->dropIndex('idx_harvests_matching_completed_at');
        });

        // Drop unit_conversions indexes
        Schema::table('unit_conversions', function (Blueprint $table) {
            $table->dropIndex('idx_unit_conversions_vegetable_unit_enabled');
            $table->dropIndex('idx_unit_conversions_vegetable_enabled_sort');
            $table->dropIndex('idx_unit_conversions_vegetable_slug');
            $table->dropIndex('idx_unit_conversions_unit');
            $table->dropIndex('idx_unit_conversions_enabled');
        });

        // Drop products indexes
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_seller_status');
            $table->dropIndex('idx_products_accept_preorders');
        });

        // Drop orders indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_preorder_id');
            $table->dropIndex('idx_orders_user_status');
        });
    }

};