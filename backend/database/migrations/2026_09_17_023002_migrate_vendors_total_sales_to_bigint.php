<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-11 task 5: vendors.total_sales is DECIMAL(10,2) while
 * every other money column in the platform is BIGINT base-currency units
 * (enhancement.md 0.1 rule 1). Backfill-safe on a populated table: round
 * the existing decimal values to whole units before changing the column
 * type, so no fractional cents are silently truncated.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE vendors SET total_sales = ROUND(total_sales)');
        DB::statement('ALTER TABLE vendors MODIFY total_sales BIGINT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendors MODIFY total_sales DECIMAL(10,2) NOT NULL DEFAULT '0.00'");
    }
};
