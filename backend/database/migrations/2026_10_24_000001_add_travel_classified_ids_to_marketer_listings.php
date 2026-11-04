<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MarketerListing / MarketerCampaignService / the public profile query all use
 * marketer_listings.classified_listing_id and travel_package_id, but no migration ever created them.
 * Classified/travel rows also have no product variant, so product_variant_id must be nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('marketer_listings', 'travel_package_id')) {
                $table->char('travel_package_id', 36)->nullable()->after('listing_category')->index();
            }
            if (! Schema::hasColumn('marketer_listings', 'classified_listing_id')) {
                $table->char('classified_listing_id', 36)->nullable()->after('listing_category')->index();
            }
            $table->char('product_variant_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            $table->dropColumn(['travel_package_id', 'classified_listing_id']);
        });
    }
};
