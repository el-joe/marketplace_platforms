<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some environments never got `campaign_category` on `marketer_campaigns`,
 * even though `MarketerCampaignService`/`MarketerCampaign` (P-14/P-15) assume
 * it exists — schema/mysql-schema.sql already has it, but the migration that
 * originally added it predates this repo's migration history on those
 * databases (same gap the 2026_09_29_120000 migration fixed for
 * travel_package_id/classified_listing_id). Idempotent: no-op wherever the
 * column is already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketer_campaigns', 'campaign_category')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->enum('campaign_category', ['product', 'travel', 'classified'])
                    ->default('product')
                    ->after('classified_listing_id')
                    ->comment('Derived from which FK is set. product = vendor/admin listing, travel = travel_package_id, classified = classified_listing_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketer_campaigns', 'campaign_category')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->dropColumn('campaign_category');
            });
        }
    }
};
