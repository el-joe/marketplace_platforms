<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The global "Fixed Commission" (settings.fixed_commission_pct + countries.fixed_commission_amount) is retired:
 * commission now comes only from category / country-category / vendor rules. Orders already placed keep the
 * commission that was stored on their lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'fixed_commission_pct')->delete();

        if (Schema::hasColumn('countries', 'fixed_commission_amount')) {
            DB::statement('ALTER TABLE `countries` DROP COLUMN `fixed_commission_amount`');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('countries', 'fixed_commission_amount')) {
            DB::statement('ALTER TABLE `countries` ADD `fixed_commission_amount` DECIMAL(19,4) NOT NULL DEFAULT 0');
        }
    }
};
