<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('flash_sale_submissions', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE flash_sale_submissions MODIFY vendor_id CHAR(36) NULL');
        DB::statement('ALTER TABLE flash_sale_submissions MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE flash_sale_submissions
            ADD CONSTRAINT chk_fss_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);

        Schema::table('flash_sale_price_histories', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE flash_sale_price_histories MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE flash_sale_price_histories
            ADD CONSTRAINT chk_fsph_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE flash_sale_price_histories DROP CHECK chk_fsph_listing_xor');

        Schema::table('flash_sale_price_histories', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE flash_sale_price_histories MODIFY vendor_listing_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE flash_sale_submissions DROP CHECK chk_fss_listing_xor');

        Schema::table('flash_sale_submissions', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE flash_sale_submissions MODIFY vendor_listing_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE flash_sale_submissions MODIFY vendor_id CHAR(36) NOT NULL');
    }
};
