<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends listing-level promo badges (vendor_listing_id) to admin and marketer
 * listings, mirroring the wishlist_items one-column-per-listing-type pattern.
 * A row with all three NULL is a product-level (admin-managed) badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_promo_badges', function (Blueprint $table) {
            $table->foreignUuid('admin_listing_id')->nullable()->after('vendor_listing_id')
                ->constrained('admin_listings')->cascadeOnDelete();
            $table->foreignUuid('marketer_listing_id')->nullable()->after('admin_listing_id')
                ->constrained('marketer_listings')->cascadeOnDelete();
            $table->index(['admin_listing_id', 'is_active', 'sort_order'], 'ppb_admin_listing_idx');
            $table->index(['marketer_listing_id', 'is_active', 'sort_order'], 'ppb_marketer_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_promo_badges', function (Blueprint $table) {
            $table->dropIndex('ppb_admin_listing_idx');
            $table->dropIndex('ppb_marketer_listing_idx');
            $table->dropConstrainedForeignId('admin_listing_id');
            $table->dropConstrainedForeignId('marketer_listing_id');
        });
    }
};
