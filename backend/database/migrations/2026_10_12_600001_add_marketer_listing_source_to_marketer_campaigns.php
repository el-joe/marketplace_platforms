<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P13 (R11): influencer-to-influencer promotion. A campaign may now promote an
 * influencer's OWN FBN listing (marketer_listings.source_listing_id IS NULL), so
 * `marketer_campaigns.marketer_listing_id` becomes the fifth campaign source.
 *
 * PromotionQuotaService already counts category 2 as campaigns with a non-null
 * marketer_listing_id, so the column name is load-bearing.
 *
 * marketer_listings.source_type gains 'marketer_listing' (the promoter's proxy points at the owner's listing).
 * The one-source-only CHECK is widened from v2 (4 sources) to v3 (5 sources).
 * Peer campaigns store: owner_type='marketer', owner_id = the listing's owner,
 * requested_by_marketer_id = the promoter. The (requested_by, owner, created_at)
 * index serves the "one promotion per promoter + owner per calendar month" rule.
 */
return new class extends Migration
{
    private function dropCheck(string $name): void
    {
        $exists = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketer_campaigns' AND CONSTRAINT_NAME = ?",
            [$name]
        );

        if (! empty($exists)) {
            DB::statement("ALTER TABLE marketer_campaigns DROP CONSTRAINT {$name}");
        }
    }

    public function up(): void
    {
        if (! Schema::hasColumn('marketer_campaigns', 'marketer_listing_id')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->uuid('marketer_listing_id')->nullable()->after('classified_listing_id');
                $table->foreign('marketer_listing_id')->references('id')->on('marketer_listings')->restrictOnDelete();
                $table->index(['requested_by_marketer_id', 'owner_id', 'created_at'], 'marketer_campaigns_peer_month_index');
            });
        }

        // The promoter's proxy listing points at the owner's listing: widen marketer_listings.source_type.
        DB::statement("ALTER TABLE marketer_listings MODIFY source_type ENUM('vendor_listing','admin_listing','marketer_listing') NULL DEFAULT NULL");

        // D5 "owner pays": for a peer promotion the owner influencer pays the promoter's commission.
        DB::statement("ALTER TABLE sub_orders MODIFY marketer_commission_owner ENUM('vendor','platform','marketer') NULL DEFAULT NULL COMMENT 'Who pays marketer_commission for this sub-order: the vendor (vendor-listing campaign), the platform (admin-listing campaign) or the owner influencer (peer promotion).'");

        $this->dropCheck('chk_campaign_listing_xor_v2');
        $this->dropCheck('chk_campaign_listing_xor_v3');

        DB::statement(<<<'SQL'
            ALTER TABLE marketer_campaigns
            ADD CONSTRAINT chk_campaign_listing_xor_v3 CHECK (
                (IF(vendor_listing_id IS NOT NULL, 1, 0)
                 + IF(admin_listing_id IS NOT NULL, 1, 0)
                 + IF(travel_package_id IS NOT NULL, 1, 0)
                 + IF(classified_listing_id IS NOT NULL, 1, 0)
                 + IF(marketer_listing_id IS NOT NULL, 1, 0)) = 1
            )
        SQL);
    }

    public function down(): void
    {
        $this->dropCheck('chk_campaign_listing_xor_v3');

        // Proxy listings of peer promotions cannot exist without the column: archive them first.
        DB::table('marketer_listings')->where('source_type', 'marketer_listing')->update(['source_type' => null, 'source_listing_id' => null, 'status' => 'archived']);
        DB::statement("ALTER TABLE marketer_listings MODIFY source_type ENUM('vendor_listing','admin_listing') NULL DEFAULT NULL");

        DB::table('sub_orders')->where('marketer_commission_owner', 'marketer')->update(['marketer_commission_owner' => 'platform']);
        DB::statement("ALTER TABLE sub_orders MODIFY marketer_commission_owner ENUM('vendor','platform') NULL DEFAULT NULL COMMENT 'Who pays marketer_commission for this sub-order: the vendor (vendor-listing campaign) or the platform (admin-listing campaign).'");

        if (Schema::hasColumn('marketer_campaigns', 'marketer_listing_id')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->dropIndex('marketer_campaigns_peer_month_index');
                $table->dropForeign(['marketer_listing_id']);
                $table->dropColumn('marketer_listing_id');
            });
        }

        DB::statement(<<<'SQL'
            ALTER TABLE marketer_campaigns
            ADD CONSTRAINT chk_campaign_listing_xor_v2 CHECK (
                (IF(vendor_listing_id IS NOT NULL, 1, 0)
                 + IF(admin_listing_id IS NOT NULL, 1, 0)
                 + IF(travel_package_id IS NOT NULL, 1, 0)
                 + IF(classified_listing_id IS NOT NULL, 1, 0)) = 1
            )
        SQL);
    }
};
