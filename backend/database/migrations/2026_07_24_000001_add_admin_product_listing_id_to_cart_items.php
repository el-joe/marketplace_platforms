<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->uuid('admin_product_listing_id')->nullable()->after('vendor_listing_id')->index();
        });

        // admin_product_listings table exists (see
        // 2026_06_02_000011_create_admin_product_listings_table.php) — FK is safe to add.
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('admin_product_listing_id')
                ->references('id')->on('admin_product_listings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });
    }
};
