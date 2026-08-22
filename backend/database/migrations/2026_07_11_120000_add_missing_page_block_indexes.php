<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PageRendererService (customer-facing, highest read-volume endpoint)
     * queries these tables per page block render. slider_slides, ad_image_items,
     * page_block_products, page_block_categories, and page_block_sellers already
     * carry a page_block_id index from their creation migrations — nothing to add
     * there. The two gaps found were:
     *
     *  - search_logs: only single-column indexes exist on query_normalized and
     *    created_at. The search_trends block filters by (country_id, created_at)
     *    together, which needs its own composite index.
     *  - flash_sale_submissions: flash_sale_id is indexed alone, but the
     *    flash_sale/product_row(flash_sale_products) hydrators filter on
     *    (flash_sale_id, status) together and sort by created_at (the schema has
     *    no `position` column, unlike the spec's assumption).
     */
    public function up(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->index(['country_id', 'created_at'], 'search_logs_country_id_created_at_index');
        });

        Schema::table('flash_sale_submissions', function (Blueprint $table) {
            $table->index(['flash_sale_id', 'status', 'created_at'], 'flash_sale_submissions_flash_sale_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropIndex('search_logs_country_id_created_at_index');
        });

        Schema::table('flash_sale_submissions', function (Blueprint $table) {
            $table->dropIndex('flash_sale_submissions_flash_sale_status_created_index');
        });
    }
};
