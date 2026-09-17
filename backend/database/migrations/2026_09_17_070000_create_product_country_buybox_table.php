<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-19 task 2: the buy-box read model.
 *
 * ProductQueryService::baseQuery() used to compute ~60 correlated subqueries
 * PER PRODUCT ROW over the whole grouped result set before LIMIT (admin /
 * vendor / marketer COALESCE chains), and had an aggregation fan-out bug
 * (vendor_listings x warehouse_inventories in the same GROUP BY inflated
 * rating_count/rating_avg for multi-warehouse listings).
 *
 * This table pre-computes, per (product, country), the deterministically
 * chosen buy-box winner and the correct aggregates (min/max price across
 * admin+vendor+marketer, total_stock, weighted rating). It is maintained by
 * BuyBoxRebuildService (called from listing observers, ListingStockChanged,
 * and rating updates) and can be fully rebuilt with `php artisan buybox:rebuild`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_country_buybox', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('country_id');

            $table->enum('listing_type', ['admin', 'vendor', 'marketer']);
            $table->uuid('listing_id');
            $table->uuid('variant_id');

            $table->bigInteger('price');
            $table->bigInteger('compare_at_price')->nullable();
            $table->bigInteger('min_price');
            $table->bigInteger('max_price');

            $table->unsignedInteger('seller_count')->default(0);
            $table->unsignedInteger('admin_listing_count')->default(0);
            $table->unsignedInteger('total_stock')->default(0);

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->string('fulfillment_model', 30)->nullable();
            $table->uuid('shipping_method_id')->nullable();
            $table->boolean('is_express')->default(false);

            $table->uuid('category_id');
            $table->uuid('brand_id')->nullable();

            $table->unsignedInteger('total_sold')->default(0);
            $table->decimal('score', 8, 4)->default(0);

            $table->timestamp('updated_at')->nullable();

            $table->primary(['product_id', 'country_id']);

            $table->index(['country_id', 'category_id', 'score']);
            $table->index(['country_id', 'category_id', 'price']);
            $table->index(['country_id', 'brand_id']);
            $table->index(['country_id', 'total_sold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_country_buybox');
    }
};
