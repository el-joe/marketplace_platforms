<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_shipping_rules', function (Blueprint $table) {
            $table->dropForeign(['vendor_listing_id']);
            $table->dropUnique('marketplace_shipping_rules_vendor_listing_id_unique');
        });

        DB::statement('ALTER TABLE marketplace_shipping_rules MODIFY vendor_listing_id CHAR(36) NULL');

        Schema::table('marketplace_shipping_rules', function (Blueprint $table) {
            $table->uuid('admin_product_listing_id')->nullable()->after('vendor_listing_id');

            $table->unique('vendor_listing_id', 'uq_msr_vendor_listing');
            $table->unique('admin_product_listing_id', 'uq_msr_admin_listing');

            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE marketplace_shipping_rules ADD CONSTRAINT chk_msr_listing_owner CHECK ((vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL) OR (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE marketplace_shipping_rules DROP CHECK chk_msr_listing_owner');

        Schema::table('marketplace_shipping_rules', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropForeign(['vendor_listing_id']);
            $table->dropUnique('uq_msr_admin_listing');
            $table->dropUnique('uq_msr_vendor_listing');
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE marketplace_shipping_rules MODIFY vendor_listing_id CHAR(36) NOT NULL');

        Schema::table('marketplace_shipping_rules', function (Blueprint $table) {
            $table->unique('vendor_listing_id', 'marketplace_shipping_rules_vendor_listing_id_unique');
            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
        });
    }
};
