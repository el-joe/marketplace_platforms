<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R8, Q9): the TravelAgencyCampaignOffer layer is replaced by plain MarketerCampaign rows created by the
 * agency (owner_type = travel_agency). Offers never produced a MarketerCampaign, so no live money depends on them.
 *
 * Irreversible: the data is deleted. down() is a deliberate no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['travel_agency_campaign_invitations', 'travel_agency_campaign_offer_packages', 'travel_agency_campaign_offers'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // Irreversible removal (v3 refactor R8): the offer tables are not recreated.
    }
};
