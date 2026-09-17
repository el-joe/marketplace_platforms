<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-03 task 4 / P-11: vendor_listings.price, compare_at_price
 * and cost_price are DECIMAL(12,2) while every other money column in the
 * schema is BIGINT base-currency units — silently truncated to int
 * elsewhere (ProductQueryService MIN/MAX, BuyBoxService, flash sales).
 *
 * Backfill-safe: round the existing decimal values to the nearest whole
 * base-currency unit *before* changing the column type, so no data is lost
 * beyond the (already-intended) whole-unit rounding.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Round in place while the columns are still DECIMAL, so the
        // subsequent type change (Doctrine DBAL's implicit cast) truncates
        // instead of rounding.
        DB::statement('UPDATE vendor_listings SET price = ROUND(price)');
        DB::statement('UPDATE vendor_listings SET compare_at_price = ROUND(compare_at_price) WHERE compare_at_price IS NOT NULL');
        DB::statement('UPDATE vendor_listings SET cost_price = ROUND(cost_price) WHERE cost_price IS NOT NULL');

        DB::statement('ALTER TABLE vendor_listings MODIFY price BIGINT NOT NULL');
        DB::statement('ALTER TABLE vendor_listings MODIFY compare_at_price BIGINT NULL');
        DB::statement('ALTER TABLE vendor_listings MODIFY cost_price BIGINT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vendor_listings MODIFY price DECIMAL(12,2) NOT NULL');
        DB::statement('ALTER TABLE vendor_listings MODIFY compare_at_price DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE vendor_listings MODIFY cost_price DECIMAL(12,2) NULL');
    }
};
