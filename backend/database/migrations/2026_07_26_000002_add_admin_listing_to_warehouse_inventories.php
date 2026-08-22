<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            // $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->nullOnDelete();
        });

        DB::statement('ALTER TABLE warehouse_inventories MODIFY vendor_listing_id CHAR(36) NULL');

        // DB::statement(<<<'SQL'
        //     ALTER TABLE warehouse_inventories
        //     ADD CONSTRAINT chk_wi_listing_xor CHECK (
        //         (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
        //         OR
        //         (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
        //     )
        // SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE warehouse_inventories DROP CHECK chk_wi_listing_xor');

        Schema::table('warehouse_inventories', function (Blueprint $table) {
            // $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE warehouse_inventories MODIFY vendor_listing_id CHAR(36) NOT NULL');
    }
};
