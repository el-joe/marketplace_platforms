<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ad_campaign_products', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE ad_campaign_products MODIFY vendor_id CHAR(36) NULL');
        DB::statement('ALTER TABLE ad_campaign_products MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE ad_campaign_products
            ADD CONSTRAINT chk_acp_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);

        Schema::table('ad_clicks', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE ad_clicks MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE ad_clicks
            ADD CONSTRAINT chk_ac_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ad_clicks DROP CHECK chk_ac_listing_xor');

        Schema::table('ad_clicks', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE ad_clicks MODIFY vendor_listing_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE ad_campaign_products DROP CHECK chk_acp_listing_xor');

        Schema::table('ad_campaign_products', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE ad_campaign_products MODIFY vendor_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE ad_campaign_products MODIFY vendor_listing_id CHAR(36) NOT NULL');
    }
};
