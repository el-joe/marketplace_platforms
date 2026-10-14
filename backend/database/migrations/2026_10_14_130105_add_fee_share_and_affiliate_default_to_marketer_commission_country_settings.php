<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADD half of the Marketers-tab rewrite (the old amount columns are dropped by a later migration, once no
 * code reads them): influencer fee-share % and the affiliate default (mode / % / flat per unit).
 * Data step: an old affiliate_commission_amount > 0 is kept as a flat per-unit default (mode fixed).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'influencer_fee_share_pct' => 'DECIMAL(7,4) NOT NULL DEFAULT 0',
        'affiliate_commission_mode' => "VARCHAR(12) NOT NULL DEFAULT 'percentage' COMMENT 'fixed | percentage | both'",
        'affiliate_commission_rate' => 'DECIMAL(7,4) NOT NULL DEFAULT 0',
        'affiliate_commission_flat' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column => $definition) {
            if (! Schema::hasColumn('marketer_commission_country_settings', $column)) {
                DB::statement("ALTER TABLE `marketer_commission_country_settings` ADD `{$column}` {$definition}");
            }
        }

        if (Schema::hasColumn('marketer_commission_country_settings', 'affiliate_commission_amount')) {
            DB::statement(
                "UPDATE `marketer_commission_country_settings`
                 SET `affiliate_commission_flat` = `affiliate_commission_amount`, `affiliate_commission_mode` = 'fixed'
                 WHERE `affiliate_commission_amount` > 0 AND `affiliate_commission_flat` = 0"
            );
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::COLUMNS) as $column) {
            if (Schema::hasColumn('marketer_commission_country_settings', $column)) {
                DB::statement("ALTER TABLE `marketer_commission_country_settings` DROP COLUMN `{$column}`");
            }
        }
    }
};
