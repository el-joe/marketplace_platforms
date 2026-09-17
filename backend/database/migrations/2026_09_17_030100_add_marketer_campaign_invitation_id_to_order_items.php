<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-12 task 1: order_items.marketer_listing_id (P-02) only
 * tells us a marketer listing was bought — it does not by itself say which
 * campaign invitation earns the commission (an independent marketer listing
 * has no invitation at all). This column records the invitation resolved at
 * attribution time (priority: marketer-listing cart item > last-click
 * referral > none), so MarketerCampaignConversion rows and payout jobs can
 * join order_items -> invitation -> campaign without re-deriving it.
 *
 * Backfill-safe: nullable, no default needed, added after order_items
 * already has rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->uuid('marketer_campaign_invitation_id')->nullable()->after('marketer_listing_id');

            $table->foreign('marketer_campaign_invitation_id')
                ->references('id')->on('marketer_campaign_invitations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['marketer_campaign_invitation_id']);
            $table->dropColumn('marketer_campaign_invitation_id');
        });
    }
};
