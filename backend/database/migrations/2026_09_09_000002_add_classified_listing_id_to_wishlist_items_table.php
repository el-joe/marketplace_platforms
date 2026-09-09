<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->uuid('classified_listing_id')->nullable()->after('admin_listing_id');
            $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->nullOnDelete();
            $table->index('classified_listing_id');
            $table->unique(['wishlist_group_id', 'classified_listing_id'], 'uq_wg_classified_listing');
        });

        // Classified listing items don't have a product variant — only vendor/admin listing items do.
        DB::statement('ALTER TABLE `wishlist_items` MODIFY `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `wishlist_items` MODIFY `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL');

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropUnique('uq_wg_classified_listing');
            $table->dropForeign(['classified_listing_id']);
            $table->dropColumn('classified_listing_id');
        });
    }
};
