<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a partner set rotating promo badges on their own listing. Rows with a
 * NULL vendor_listing_id stay product-level (admin-managed); a listing that has
 * its own active badges shows those instead of the product-level ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_promo_badges', function (Blueprint $table) {
            $table->foreignUuid('vendor_listing_id')->nullable()->after('product_id')
                ->constrained('vendor_listings')->cascadeOnDelete();
            $table->index(['vendor_listing_id', 'is_active', 'sort_order'], 'ppb_listing_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_promo_badges', function (Blueprint $table) {
            $table->dropIndex('ppb_listing_active_sort_idx');
            $table->dropConstrainedForeignId('vendor_listing_id');
        });
    }
};
