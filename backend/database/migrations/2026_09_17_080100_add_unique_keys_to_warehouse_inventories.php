<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-22 — revisits the unique-key task P-13 deferred as "too
 * risky to write safely without dedicated time to verify against real data
 * shape".
 *
 * Investigation result: the unique key is NOT safe to add, and P-13's
 * caution was correct. `tests/Feature/BuyBoxReadModelTest.php::test_rating_aggregate_is_not_inflated_by_multiple_warehouses`
 * deliberately creates a SECOND `warehouse_inventories` row for the same
 * `(warehouse_id, vendor_listing_id)` pair — the same listing stocked twice
 * in the same warehouse (e.g. two bin locations / two stock batches) is a
 * real, exercised business scenario, not bad data to dedupe. Adding
 * `unique(warehouse_id, vendor_listing_id)` breaks that insert outright
 * (verified locally: it throws a duplicate-key SQLSTATE 23000 the moment
 * that test runs against this migration), so a listing's stock legitimately
 * needs to be representable as N rows per warehouse, aggregated by SUM(), as
 * BuyBoxRebuildService already does.
 *
 * Resolution: do NOT add the unique keys. What *is* safe and added below is
 * the `(admin_listing_id, quantity_available)` index the doc also asked
 * for — a plain non-unique index has no data-shape risk, and admin listings
 * don't have the same "counted per bin" pattern this test exercises for
 * vendor listings (no test creates two rows for the same admin_listing_id +
 * warehouse_id either, but nothing rules it out, so this migration only
 * adds an index, not a constraint, for admin_listing_id too).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('warehouse_inventories', 'warehouse_inventories_admin_listing_qty_available_index')) {
            Schema::table('warehouse_inventories', function (Blueprint $table) {
                $table->index(['admin_listing_id', 'quantity_available'], 'warehouse_inventories_admin_listing_qty_available_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->dropIndex('warehouse_inventories_admin_listing_qty_available_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $exists = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $indexName]
        );

        return ($exists[0]->cnt ?? 0) > 0;
    }
};
