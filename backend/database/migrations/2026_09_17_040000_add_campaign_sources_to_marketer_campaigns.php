<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-14 task 1.
 *
 * `marketer_campaigns.campaign_category` (enum product/travel/classified),
 * `travel_package_id`, `classified_listing_id`, and a CHECK constraint
 * enforcing "exactly one of vendor_listing_id/admin_listing_id/
 * travel_package_id/classified_listing_id is set"
 * (`chk_campaign_listing_xor_v2`, added by an earlier migration and
 * present in database/schema/mysql-schema.sql) ALREADY EXIST on this
 * table. This project's MySQL 8.0.46 (confirmed via `SELECT @@version`)
 * does support a CHECK constraint for this, and an earlier migration
 * already added exactly that — so this migration does not duplicate it,
 * it only adds what's still missing:
 *
 * - `owner_type`/`owner_id`: who owns the campaign (and therefore pays
 *   marketer commission, per D5) — 'vendor' (owner_id = vendors.id),
 *   'platform' (owner_id null), or transiently 'marketer' while a
 *   marketer-originated request awaits the real owner's approval (see
 *   MarketerCampaignService::requestCampaign()/approveMarketerRequest()).
 *   `campaign_category` already tells us WHAT is promoted; owner_type
 *   tells us WHO is promoting it — a vendor-listing campaign_category
 *   of 'product' says nothing about whether it's vendor- or
 *   marketer-requested, for instance.
 * - `requested_by_marketer_id`: kept after approval so
 *   approveMarketerRequest() knows which marketer to auto-accept even
 *   though owner_id has by then been overwritten with the real owner.
 * - `vendor_id` becomes nullable: platform (admin-listing) campaigns
 *   have no vendor.
 * - `status` gains 'marketer_requested', distinct from 'pending_admin'
 *   (admin review) — a marketer-originated request is reviewed by the
 *   listing's owner (vendor or admin), not necessarily by an admin.
 *
 * Backfill: every existing row is vendor-owned (vendor_id was NOT NULL
 * until this migration), so owner_type/owner_id backfill unambiguously
 * before any column is tightened — 0.1 rule 2 (backfill-safe on a
 * database that already holds data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->string('owner_type', 20)->nullable()->after('vendor_id');
            $table->char('owner_id', 36)->nullable()->after('owner_type');
            $table->char('requested_by_marketer_id', 36)->nullable()->after('owner_id')
                ->comment('P-14: set when this campaign was marketer-originated. Kept after approval so approveMarketerRequest() knows which marketer to auto-accept.');
        });

        DB::table('marketer_campaigns')->whereNull('owner_type')->update(['owner_type' => 'vendor']);
        DB::statement('UPDATE marketer_campaigns SET owner_id = vendor_id WHERE owner_id IS NULL');

        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->string('owner_type', 20)->nullable(false)->change();
            $table->char('vendor_id', 36)->nullable()->change();

            $table->index(['owner_type', 'owner_id']);
        });

        DB::statement("ALTER TABLE marketer_campaigns MODIFY status ENUM('pending_admin','marketer_requested','active','auto_approved','rejected','paused','done','cancelled') NOT NULL DEFAULT 'pending_admin'");
    }

    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropIndex(['owner_type', 'owner_id']);
            $table->dropColumn(['owner_type', 'owner_id', 'requested_by_marketer_id']);
            $table->char('vendor_id', 36)->nullable(false)->change();
        });

        DB::statement("ALTER TABLE marketer_campaigns MODIFY status ENUM('pending_admin','active','auto_approved','rejected','paused','done','cancelled') NOT NULL DEFAULT 'pending_admin'");
    }
};
