<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2: the flat per-sale amounts of the category Marketers tab are replaced by influencer_fee_share_pct and the
 * affiliate default (mode / rate / flat). No code reads the old columns any more; their values were dumped by
 * 2026_10_14_150001_dump_legacy_marketer_commission_amounts.
 */
return new class extends Migration
{
    private const TABLE = 'marketer_commission_country_settings';

    public function up(): void
    {
        foreach (['influencer_commission_amount', 'affiliate_commission_amount'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement('ALTER TABLE `'.self::TABLE.'` DROP COLUMN `'.$column.'`');
            }
        }
    }

    public function down(): void
    {
        foreach (['influencer_commission_amount', 'affiliate_commission_amount'] as $column) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                DB::statement('ALTER TABLE `'.self::TABLE.'` ADD `'.$column.'` DECIMAL(19,4) NOT NULL DEFAULT 0');
            }
        }
    }
};
