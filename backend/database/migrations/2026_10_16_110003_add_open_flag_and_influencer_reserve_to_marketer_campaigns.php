<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R2), campaign level.
 *
 * Already in place (no change needed):
 *  - `campaign_category` (product | travel | classified) is the campaign vertical ("listing type"); exposed on the
 *    model as `listing_type`.
 *  - `owner_type` is VARCHAR(20) with no enum/CHECK, so 'travel_agency' (owner_id = travel_agencies.id,
 *    vendor_id NULL) needs no schema change.
 *
 * Added:
 *  - open_to_affiliates: campaign is open to every registered affiliate AND broker (brokers merged in).
 *  - influencer_fixed_reserved: sum of the per-campaign influencer fixed amounts currently reserved from
 *    max_commission_budget (snapshots on accepted invitations), so the remaining budget funds affiliate/broker
 *    commissions only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketer_campaigns', 'open_to_affiliates')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->boolean('open_to_affiliates')->default(false)->comment('Open to all registered affiliates and brokers');
            });
        }

        if (! Schema::hasColumn('marketer_campaigns', 'influencer_fixed_reserved')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->decimal('influencer_fixed_reserved', 19, 4)->default(0)->comment('Influencer fixed amounts reserved from max_commission_budget');
            });
        }
    }

    public function down(): void
    {
        foreach (['open_to_affiliates', 'influencer_fixed_reserved'] as $column) {
            if (Schema::hasColumn('marketer_campaigns', $column)) {
                Schema::table('marketer_campaigns', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
