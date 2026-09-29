<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Some environments never got `travel_package_id`/`classified_listing_id`
 * on `marketer_campaigns` even though later migrations (P-14/P-15) assume
 * they exist — schema/mysql-schema.sql already has them, but the migration
 * that originally added them predates this repo's migration history on
 * those databases. This migration is idempotent so it's a no-op wherever
 * the columns are already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketer_campaigns', 'travel_package_id')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->uuid('travel_package_id')->nullable()->after('admin_listing_id');
                $table->foreign('travel_package_id')->references('id')->on('travel_packages')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('marketer_campaigns', 'classified_listing_id')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->uuid('classified_listing_id')->nullable()->after('travel_package_id');
                $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->restrictOnDelete();
            });
        }

        $constraints = DB::select(<<<'SQL'
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'marketer_campaigns'
              AND CONSTRAINT_NAME = 'chk_campaign_listing_xor_v2'
        SQL);

        if (empty($constraints)) {
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
    }

    public function down(): void
    {
        $constraints = DB::select(<<<'SQL'
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'marketer_campaigns'
              AND CONSTRAINT_NAME = 'chk_campaign_listing_xor_v2'
        SQL);

        if (! empty($constraints)) {
            DB::statement('ALTER TABLE marketer_campaigns DROP CHECK chk_campaign_listing_xor_v2');
        }

        if (Schema::hasColumn('marketer_campaigns', 'classified_listing_id')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->dropForeign(['classified_listing_id']);
                $table->dropColumn('classified_listing_id');
            });
        }

        if (Schema::hasColumn('marketer_campaigns', 'travel_package_id')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->dropForeign(['travel_package_id']);
                $table->dropColumn('travel_package_id');
            });
        }
    }
};
