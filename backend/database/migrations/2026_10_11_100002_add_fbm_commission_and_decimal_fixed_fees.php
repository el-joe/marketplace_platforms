<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client: the fixed platform fee can be fractional (e.g. 1.9 in the selected currency) and FBM needs
 * its own commission pair. Fixed-fee columns become DECIMAL(19,4) (same as order money) and
 * commission_fbm_pct / commission_fbm_fixed are added. FBM values left empty fall back to the FBP pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sub_orders', 'commission_vat')) {
            DB::statement('ALTER TABLE `sub_orders` ADD `commission_vat` DECIMAL(19,4) NOT NULL DEFAULT 0 AFTER `platform_commission`');
            DB::statement('ALTER TABLE `sub_orders` ADD `commission_vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `commission_vat`');
        }

        foreach (['categories' => false, 'country_categories' => true] as $table => $nullable) {
            foreach (['commission_fbn_fixed', 'commission_fbp_fixed'] as $column) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(19,4) ".($nullable ? 'NULL DEFAULT NULL' : 'NOT NULL DEFAULT 0'));
            }

            if (! Schema::hasColumn($table, 'commission_fbm_pct')) {
                DB::statement("ALTER TABLE `{$table}` ADD `commission_fbm_pct` DECIMAL(5,2) NULL DEFAULT NULL AFTER `commission_fbp_fixed`");
                DB::statement("ALTER TABLE `{$table}` ADD `commission_fbm_fixed` DECIMAL(19,4) NULL DEFAULT NULL AFTER `commission_fbm_pct`");
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `sub_orders` DROP COLUMN `commission_vat`, DROP COLUMN `commission_vat_rate`');

        foreach (['categories', 'country_categories'] as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `commission_fbm_pct`, DROP COLUMN `commission_fbm_fixed`");
            foreach (['commission_fbn_fixed', 'commission_fbp_fixed'] as $column) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT ".($table === 'categories' ? 'NOT NULL DEFAULT 0' : 'NULL DEFAULT NULL'));
            }
        }
    }
};
