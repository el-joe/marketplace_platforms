<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R7 + R8): per-vertical bookkeeping for classified and travel campaigns.
 *
 *  - classified_listings.campaign_enabled / travel_packages.campaign_enabled: true while a non-terminal marketer
 *    campaign promotes the item (same flag vendor_listings already carries; kept in sync by
 *    MarketerCampaignService::syncListingCampaignEnabled).
 *  - classified_listings.sold_buyer_note: optional free-text note the seller can leave when marking an item sold
 *    through a campaign (who bought it, how the deal closed).
 *
 * Additive and nullable / defaulted, safe on live data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('classified_listings', 'campaign_enabled')) {
            Schema::table('classified_listings', fn (Blueprint $t) => $t->boolean('campaign_enabled')->default(false)->comment('A non-terminal marketer campaign promotes this listing'));
        }
        if (! Schema::hasColumn('classified_listings', 'sold_buyer_note')) {
            Schema::table('classified_listings', fn (Blueprint $t) => $t->text('sold_buyer_note')->nullable()->comment('Optional note entered when marked sold'));
        }
        if (! Schema::hasColumn('travel_packages', 'campaign_enabled')) {
            Schema::table('travel_packages', fn (Blueprint $t) => $t->boolean('campaign_enabled')->default(false)->comment('A non-terminal marketer campaign promotes this package'));
        }
    }

    public function down(): void
    {
        foreach ([['classified_listings', 'campaign_enabled'], ['classified_listings', 'sold_buyer_note'], ['travel_packages', 'campaign_enabled']] as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
