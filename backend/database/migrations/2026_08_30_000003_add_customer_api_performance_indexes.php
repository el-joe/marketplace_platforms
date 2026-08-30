<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->index(['country_id', 'status', 'deleted_at'], 'vendor_listings_country_status_deleted_index');
            $table->index(['country_id', 'status', 'score'], 'vendor_listings_country_status_score_index');
            $table->index(['product_variant_id', 'country_id', 'status'], 'vendor_listings_variant_country_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'category_id'], 'products_status_category_id_index');
            $table->index(['status', 'brand_id'], 'products_status_brand_id_index');
            $table->index(['status', 'is_featured'], 'products_status_is_featured_index');
            $table->index(['status', 'total_sold'], 'products_status_total_sold_index');
            $table->index(['status', 'published_at'], 'products_status_published_at_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->index(['product_variant_id', 'position'], 'product_images_variant_position_index');
            $table->index(['product_id', 'position'], 'product_images_product_position_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'placed_at'], 'orders_customer_placed_at_index');
            $table->index(['customer_id', 'status'], 'orders_customer_status_index');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->index(['order_id', 'status'], 'sub_orders_order_status_index');
        });

        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'warranty_claims_customer_created_index');
            $table->index(['order_item_id', 'status'], 'warranty_claims_order_item_status_index');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->index('session_token', 'carts_session_token_index');
            $table->index(['user_id', 'country_id'], 'carts_user_country_index');
        });

        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->index(['vendor_listing_id', 'quantity_available'], 'warehouse_inventories_listing_qty_available_index');
        });

        Schema::table('product_country_settings', function (Blueprint $table) {
            $table->index(['country_id', 'is_available', 'product_id'], 'product_country_settings_country_avail_product_index');
        });

        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->index(['customer_id', 'status', 'created_at'], 'warranty_purchases_customer_status_created_index');
        });

        if (Schema::hasTable('search_suggestions')) {
            Schema::table('search_suggestions', function (Blueprint $table) {
                $table->index(['country_id', 'is_blocked', 'keyword_normalized'], 'search_suggestions_country_blocked_keyword_index');
            });
        }

        // FULLTEXT index for MATCH...AGAINST search (Part 6). Requires InnoDB fulltext support (MySQL 5.6+).
        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_fulltext_search (name_en, name_ar, short_desc_en, model_number)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP INDEX products_fulltext_search');

        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropIndex('vendor_listings_country_status_deleted_index');
            $table->dropIndex('vendor_listings_country_status_score_index');
            $table->dropIndex('vendor_listings_variant_country_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_category_id_index');
            $table->dropIndex('products_status_brand_id_index');
            $table->dropIndex('products_status_is_featured_index');
            $table->dropIndex('products_status_total_sold_index');
            $table->dropIndex('products_status_published_at_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_variant_position_index');
            $table->dropIndex('product_images_product_position_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_placed_at_index');
            $table->dropIndex('orders_customer_status_index');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropIndex('sub_orders_order_status_index');
        });

        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropIndex('warranty_claims_customer_created_index');
            $table->dropIndex('warranty_claims_order_item_status_index');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex('carts_session_token_index');
            $table->dropIndex('carts_user_country_index');
        });

        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->dropIndex('warehouse_inventories_listing_qty_available_index');
        });

        Schema::table('product_country_settings', function (Blueprint $table) {
            $table->dropIndex('product_country_settings_country_avail_product_index');
        });

        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->dropIndex('warranty_purchases_customer_status_created_index');
        });

        if (Schema::hasTable('search_suggestions')) {
            Schema::table('search_suggestions', function (Blueprint $table) {
                $table->dropIndex('search_suggestions_country_blocked_keyword_index');
            });
        }
    }
};
