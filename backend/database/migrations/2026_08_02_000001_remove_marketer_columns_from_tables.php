<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // categories.marketer_sample_quota
        if (Schema::hasColumn('categories', 'marketer_sample_quota')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('marketer_sample_quota');
            });
        }

        // classified_listings.marketer_promotion_enabled
        if (Schema::hasColumn('classified_listings', 'marketer_promotion_enabled')) {
            Schema::table('classified_listings', function (Blueprint $table) {
                $table->dropColumn('marketer_promotion_enabled');
            });
        }

        // classified_inquiries.marketer_id
        if (Schema::hasColumn('classified_inquiries', 'marketer_id')) {
            Schema::table('classified_inquiries', function (Blueprint $table) {
                $table->dropForeign(['marketer_id']);
                $table->dropColumn('marketer_id');
            });
        }

        // influencer_deals.marketer_id — required FK + composite index ['marketer_id', 'status']
        if (Schema::hasColumn('influencer_deals', 'marketer_id')) {
            Schema::table('influencer_deals', function (Blueprint $table) {
                $table->dropForeign(['marketer_id']);
                $table->dropIndex(['marketer_id', 'status']);
                $table->dropColumn('marketer_id');
            });
        }

        // influencer_media_kits.marketer_id — required FK + unique constraint
        if (Schema::hasColumn('influencer_media_kits', 'marketer_id')) {
            Schema::table('influencer_media_kits', function (Blueprint $table) {
                $table->dropForeign(['marketer_id']);
                $table->dropUnique(['marketer_id']);
                $table->dropColumn('marketer_id');
            });
        }

        // affiliate_promo_codes.marketer_id — required FK + composite index ['marketer_id', 'is_active']
        if (Schema::hasColumn('affiliate_promo_codes', 'marketer_id')) {
            Schema::table('affiliate_promo_codes', function (Blueprint $table) {
                $table->dropForeign(['marketer_id']);
                $table->dropIndex(['marketer_id', 'is_active']);
                $table->dropColumn('marketer_id');
            });
        }

        // support_tickets.requester_role enum — drop the 'marketer' option
        if (Schema::hasColumn('support_tickets', 'requester_role')) {
            DB::statement("ALTER TABLE support_tickets MODIFY COLUMN requester_role ENUM('customer', 'seller', 'delivery_agent', 'shipping_supervisor', 'travel_agency') NOT NULL");
        }

        // ai_feature_credits.owner_type enum — drop the 'marketer' option
        if (Schema::hasColumn('ai_feature_credits', 'owner_type')) {
            DB::statement("ALTER TABLE ai_feature_credits MODIFY COLUMN owner_type ENUM('vendor') NOT NULL");
        }

        // ai_video_generation_jobs.requested_by_type enum — drop the 'marketer' option
        if (Schema::hasColumn('ai_video_generation_jobs', 'requested_by_type')) {
            DB::statement("ALTER TABLE ai_video_generation_jobs MODIFY COLUMN requested_by_type ENUM('vendor') NOT NULL");
        }

        // ledger_entries.account_holder_type is a plain VARCHAR(50), not an enum — no schema change needed.

        // Note: orders, order_items, admin_product_listings and countries carry no
        // marketer_id / marketer_campaign_id / available_for_marketers /
        // marketer_withholding_tax_rate columns in this schema — nothing to drop there.
        //
        // order_items commission snapshot columns (commission_fixed_cents, commission_rate_pct,
        // commission_category_id) are shared vendor commission tracking columns — kept.
        //
        // vendor_listings / admin_product_listings / categories influencer_* and affiliate_*
        // columns power the standalone vendor-funded influencer/affiliate promotion feature
        // (see app/Services/Vendor/ListingService.php, app/Models/VendorListing.php) and are
        // not tied to the marketer actor being removed — kept.
    }

    public function down(): void
    {
        // No rollback — this is a clean removal.
    }
};
