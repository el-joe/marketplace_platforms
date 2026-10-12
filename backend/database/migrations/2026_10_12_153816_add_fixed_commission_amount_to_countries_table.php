<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global "fixed commission": one percentage for the whole platform (settings.fixed_commission_pct)
 * plus one flat per-unit amount per country, charged on top of the category ("dynamic") commission.
 * Same DECIMAL(19,4) precision as the other fixed fees so fractional amounts (e.g. 1.9) are exact.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('countries', 'fixed_commission_amount')) {
            DB::statement('ALTER TABLE `countries` ADD `fixed_commission_amount` DECIMAL(19,4) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('countries', 'fixed_commission_amount')) {
            DB::statement('ALTER TABLE `countries` DROP COLUMN `fixed_commission_amount`');
        }
    }
};
