<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R6): vendor payout statement lines of the new campaign model.
 *
 *  - campaign_fee:               the platform campaign fee (fixed + variable) of one campaign. payout_items.campaign_id
 *                                is UNIQUE, so a campaign fee can be on at most one statement.
 *  - campaign_budget_commission: commission paid out of a campaign's Max Commission Budget: one line per approved
 *                                affiliate/broker conversion (marketer_conversion_id) or per influencer fixed amount
 *                                paid at completion (invitation_id, already UNIQUE).
 *
 * The legacy item types stay in the ENUM until the final removal phase.
 */
return new class extends Migration
{
    private const OLD_ENUM = "ENUM('sub_order','marketer_commission','promotion_sample','influencer_fee','affiliate_marketing_fee')";

    private const NEW_ENUM = "ENUM('sub_order','marketer_commission','promotion_sample','influencer_fee','affiliate_marketing_fee','campaign_fee','campaign_budget_commission')";

    public function up(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `payout_items` MODIFY COLUMN `item_type` '.self::NEW_ENUM." NOT NULL DEFAULT 'sub_order'");
        }

        if (! Schema::hasColumn('payout_items', 'campaign_id')) {
            Schema::table('payout_items', function (Blueprint $table) {
                $table->foreignUuid('campaign_id')->nullable()->constrained('marketer_campaigns')->nullOnDelete();
                $table->unique('campaign_id', 'payout_items_campaign_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payout_items', 'campaign_id')) {
            Schema::table('payout_items', function (Blueprint $table) {
                $table->dropUnique('payout_items_campaign_id_unique');
                $table->dropConstrainedForeignId('campaign_id');
            });
        }

        if ($this->isMysql()) {
            DB::table('payout_items')->whereIn('item_type', ['campaign_fee', 'campaign_budget_commission'])->delete();
            DB::statement('ALTER TABLE `payout_items` MODIFY COLUMN `item_type` '.self::OLD_ENUM." NOT NULL DEFAULT 'sub_order'");
        }
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
