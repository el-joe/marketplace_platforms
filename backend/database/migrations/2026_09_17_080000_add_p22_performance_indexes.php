<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-22 task 3 — index migration based on the query patterns
 * PDP/cart/checkout/orders-list actually run, checked against
 * information_schema.statistics on marketplace_test first so we don't add
 * duplicates. What was already covered by P-02/P-13/P-15/P-17/P-19/P-20/P-21
 * migrations (verified below, not re-added):
 *
 *  - order_items.marketer_listing_id            -> order_items_marketer_listing_id_index (P-02)
 *  - marketer_listings(source_type, source_listing_id) -> ml_source_idx (P-15) — covers
 *    lookups filtered by source_listing_id together with source_type; the doc's plain
 *    (source_listing_id) index would be redundant on top of it, so it is skipped.
 *  - sub_orders(order_id, status)                -> sub_orders_order_status_index (P-?)
 *  - products FULLTEXT                            -> products_fulltext_search (P-19)
 *  - product_images(product_variant_id, position) and (product_id, position) already
 *    exist (2026_08_30 migration) and product_images.product_variant_id already has an
 *    FK (P-17, 2026_09_17_000001_add_fk_product_images_product_variant_id.php).
 *
 * Genuinely missing, added here:
 *  - product_images(product_variant_id, is_primary, position): ListingImageResolver's
 *    hot query filters by variant_id and orders by is_primary DESC, position ASC — the
 *    existing (variant_id, position) index can't use is_primary as a sort/filter column,
 *    so MySQL falls back to a filesort on non-trivial row counts.
 *  - order_items(sub_order_id, fulfillment_status): fulfillment/shipping-status rollups
 *    per sub-order (orders list, sub-order fulfillment screens) filter on both columns;
 *    only a plain sub_order_id index exists today.
 *  - sub_orders(vendor_id, status, delivered_at): payout batching queries (P-04/P-12
 *    payout jobs) filter by vendor + status and range on delivered_at.
 *  - coupons(code, is_active): checkout coupon lookup filters by code AND is_active;
 *    only a unique index on code alone exists, so the is_active filter isn't covered.
 *  - coupon_usages(coupon_id, customer_id): per-customer coupon usage caps at checkout;
 *    only (coupon_id) and (coupon_id, status) exist today, no customer_id column pairing.
 *  - categories(parent_id, is_active, is_visible, sort_order): CategoryService's nav/browse
 *    tree (P-21) filters on all three flags and orders by sort_order per parent; only a
 *    plain parent_id index exists.
 *  - page_blocks(page_id, section_id, is_visible, position): PageBuilderService's block
 *    fetch filters by page + section + visibility and orders by position.
 *
 * Redundant indexes removed (page_blocks has 13 keys; a composite index already serves
 * any query that only needs the leftmost column):
 *  - page_blocks_page_id_index      -> superseded by page_blocks_page_id_position_index
 *  - page_blocks_section_id_index   -> superseded by page_blocks_section_id_position_index
 *
 * warehouse_inventories unique keys: see the dedicated migration
 * 2026_09_17_080100_add_unique_keys_to_warehouse_inventories.php for the
 * P-13-deferred (warehouse_id, vendor_listing_id) / (warehouse_id, admin_listing_id)
 * unique constraints and the dedup investigation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('product_images', 'product_images_variant_primary_position_index', function (Blueprint $t) {
            $t->index(['product_variant_id', 'is_primary', 'position'], 'product_images_variant_primary_position_index');
        });

        $this->addIndexIfMissing('order_items', 'order_items_sub_order_fulfillment_status_index', function (Blueprint $t) {
            $t->index(['sub_order_id', 'fulfillment_status'], 'order_items_sub_order_fulfillment_status_index');
        });

        $this->addIndexIfMissing('sub_orders', 'sub_orders_vendor_status_delivered_at_index', function (Blueprint $t) {
            $t->index(['vendor_id', 'status', 'delivered_at'], 'sub_orders_vendor_status_delivered_at_index');
        });

        $this->addIndexIfMissing('coupons', 'coupons_code_is_active_index', function (Blueprint $t) {
            $t->index(['code', 'is_active'], 'coupons_code_is_active_index');
        });

        $this->addIndexIfMissing('coupon_usages', 'coupon_usages_coupon_customer_index', function (Blueprint $t) {
            $t->index(['coupon_id', 'customer_id'], 'coupon_usages_coupon_customer_index');
        });

        $this->addIndexIfMissing('categories', 'categories_parent_active_visible_sort_index', function (Blueprint $t) {
            $t->index(['parent_id', 'is_active', 'is_visible', 'sort_order'], 'categories_parent_active_visible_sort_index');
        });

        $this->addIndexIfMissing('page_blocks', 'page_blocks_page_section_visible_position_index', function (Blueprint $t) {
            $t->index(['page_id', 'section_id', 'is_visible', 'position'], 'page_blocks_page_section_visible_position_index');
        });

        $this->dropIndexIfExists('page_blocks', 'page_blocks_page_id_index');
        $this->dropIndexIfExists('page_blocks', 'page_blocks_section_id_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('product_images', 'product_images_variant_primary_position_index');
        $this->dropIndexIfExists('order_items', 'order_items_sub_order_fulfillment_status_index');
        $this->dropIndexIfExists('sub_orders', 'sub_orders_vendor_status_delivered_at_index');
        $this->dropIndexIfExists('coupons', 'coupons_code_is_active_index');
        $this->dropIndexIfExists('coupon_usages', 'coupon_usages_coupon_customer_index');
        $this->dropIndexIfExists('categories', 'categories_parent_active_visible_sort_index');
        $this->dropIndexIfExists('page_blocks', 'page_blocks_page_section_visible_position_index');

        $this->addIndexIfMissing('page_blocks', 'page_blocks_page_id_index', function (Blueprint $t) {
            $t->index('page_id', 'page_blocks_page_id_index');
        });
        $this->addIndexIfMissing('page_blocks', 'page_blocks_section_id_index', function (Blueprint $t) {
            $t->index('section_id', 'page_blocks_section_id_index');
        });
    }

    private function addIndexIfMissing(string $table, string $indexName, \Closure $callback): void
    {
        $exists = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $indexName]
        );

        if (($exists[0]->cnt ?? 0) === 0) {
            Schema::table($table, $callback);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $indexName]
        );

        if (($exists[0]->cnt ?? 0) > 0) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($indexName));
        }
    }
};
