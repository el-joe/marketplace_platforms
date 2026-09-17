<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-02 task 4: decide how an admin-listing sub-order records
 * its `vendor_id` (previously NOT NULL on both `sub_orders` and
 * `order_items`, which crashed placeOrder for any admin-listing item).
 *
 * DECISION (documented per the spec's two options): we chose the first
 * option — make `vendor_id` nullable and add a `seller_type` enum
 * ('vendor','platform') — rather than seeding a synthetic "platform vendor"
 * row. Reasoning: a seeded platform-vendor row would show up in every
 * vendor-scoped report/listing/join unless every one of those call sites is
 * audited to exclude it, which is a much larger blast radius than adding one
 * nullable column + one enum that payout/reporting code can branch on
 * explicitly. `seller_type` defaults to 'vendor' for all existing rows
 * (backfilled before the column is added, so this works on a table that
 * already has data), and is set to 'platform' by the checkout transaction
 * whenever `vendor_id` is null.
 */
return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal isn't installed, so ->change() is unavailable — use
        // raw DDL (MySQL-specific, matching every other column in this
        // table) instead of Blueprint::change().
        DB::statement("ALTER TABLE sub_orders MODIFY vendor_id char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        DB::statement("ALTER TABLE sub_orders ADD COLUMN seller_type ENUM('vendor','platform') NOT NULL DEFAULT 'vendor' AFTER vendor_id");

        DB::statement("ALTER TABLE order_items MODIFY vendor_id char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn('seller_type');
        });

        DB::statement("UPDATE sub_orders SET vendor_id = '' WHERE vendor_id IS NULL");
        DB::statement("ALTER TABLE sub_orders MODIFY vendor_id char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");

        DB::statement("UPDATE order_items SET vendor_id = '' WHERE vendor_id IS NULL");
        DB::statement("ALTER TABLE order_items MODIFY vendor_id char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }
};
