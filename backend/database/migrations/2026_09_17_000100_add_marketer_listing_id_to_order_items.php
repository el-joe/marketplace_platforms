<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-02 task 1: order_items has no marketer_listing_id column
 * — the marketer link only lived inside the product_snapshot JSON, so
 * attribution/reports/returns couldn't join on it. Add the column and
 * backfill it from existing product_snapshot->marketer_listing_id values
 * (this table already holds data, so backfill runs before the FK/index is
 * relied upon by anything new).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->char('marketer_listing_id', 36)->nullable()->after('admin_listing_id');
        });

        // Backfill from the JSON snapshot before adding the FK/index, so a
        // production table with existing rows never fails the migration.
        DB::table('order_items')
            ->whereNotNull('product_snapshot->marketer_listing_id')
            ->select('id', 'product_snapshot')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $snapshot = json_decode($row->product_snapshot, true) ?? [];
                    $marketerListingId = $snapshot['marketer_listing_id'] ?? null;

                    if ($marketerListingId) {
                        DB::table('order_items')
                            ->where('id', $row->id)
                            ->update(['marketer_listing_id' => $marketerListingId]);
                    }
                }
            });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('marketer_listing_id')
                  ->references('id')->on('marketer_listings')
                  ->nullOnDelete();
            $table->index('marketer_listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['marketer_listing_id']);
            $table->dropColumn('marketer_listing_id');
        });
    }
};
