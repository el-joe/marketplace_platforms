<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statement lines for influencer fees carry the marketing-fee part separately, so
 * fee + VAT (vat_amount) + marketing_fee_amount reconciles to abs(net).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payout_items', 'marketing_fee_amount')) {
            DB::statement('ALTER TABLE `payout_items` ADD `marketing_fee_amount` DECIMAL(19,4) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payout_items', 'marketing_fee_amount')) {
            DB::statement('ALTER TABLE `payout_items` DROP COLUMN `marketing_fee_amount`');
        }
    }
};
