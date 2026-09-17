<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-15 task 1: marketer_listings must resolve to a concrete
 * sellable source (a vendor_listing or admin_listing) instead of relying on
 * ad-hoc buy-box fallback lookups at checkout time (P-02's
 * CartLineSource::resolveIndependentMarketerFulfilment(), now removed).
 *
 * `paused_reason` lets the availability-sync observers (P-15 task 4) tell a
 * listing they auto-paused apart from one the marketer paused manually, so
 * they only ever auto-unpause the former.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('marketer_listings', 'source_type')) {
                $table->enum('source_type', ['vendor_listing', 'admin_listing'])
                    ->nullable()
                    ->after('invitation_id');
            }
            if (! Schema::hasColumn('marketer_listings', 'source_listing_id')) {
                $table->uuid('source_listing_id')->nullable()->after('source_type');
                $table->index(['source_type', 'source_listing_id'], 'ml_source_idx');
            }
            if (! Schema::hasColumn('marketer_listings', 'paused_reason')) {
                $table->string('paused_reason', 30)->nullable()->after('status')
                    ->comment('manual = marketer paused it themselves; source_unavailable = auto-paused by the availability sync (P-15)');
            }
        });

        // Backfill from the invitation's campaign for every existing
        // campaign-linked row: invitation -> campaign -> vendor_listing_id
        // OR admin_listing_id (P-14 owner/source columns), whichever is set.
        DB::statement(<<<'SQL'
            UPDATE marketer_listings ml
            INNER JOIN marketer_campaign_invitations mci ON mci.id = ml.invitation_id
            INNER JOIN marketer_campaigns mc ON mc.id = mci.campaign_id
            SET ml.source_type = 'vendor_listing', ml.source_listing_id = mc.vendor_listing_id
            WHERE ml.invitation_id IS NOT NULL
              AND mc.vendor_listing_id IS NOT NULL
              AND ml.source_listing_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE marketer_listings ml
            INNER JOIN marketer_campaign_invitations mci ON mci.id = ml.invitation_id
            INNER JOIN marketer_campaigns mc ON mc.id = mci.campaign_id
            SET ml.source_type = 'admin_listing', ml.source_listing_id = mc.admin_listing_id
            WHERE ml.invitation_id IS NOT NULL
              AND mc.admin_listing_id IS NOT NULL
              AND ml.source_listing_id IS NULL
        SQL);

        // Any row that still has no resolvable source (independent listing
        // created before this migration, or a campaign whose source was a
        // travel package / classified listing, which have no checkout
        // fulfilment concept) is not purchasable — hide it rather than leave
        // it silently broken at checkout.
        DB::table('marketer_listings')
            ->whereNull('source_listing_id')
            ->where('listing_category', 'product')
            ->update(['status' => 'paused', 'paused_reason' => 'source_unavailable']);
    }

    public function down(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            $table->dropIndex('ml_source_idx');
            $table->dropColumn(['source_type', 'source_listing_id', 'paused_reason']);
        });
    }
};
