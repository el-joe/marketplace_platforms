<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Supersedes the `wishlists` and `whishlists` (typo) tables — left in place, unused, deprecated.
        Schema::create('wishlist_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index(['customer_id', 'sort_order']);
            $table->index(['customer_id', 'is_default']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wishlist_group_id');
            $table->uuid('customer_id');
            $table->uuid('vendor_listing_id')->nullable();
            $table->uuid('admin_product_listing_id')->nullable();
            $table->uuid('product_variant_id');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->foreign('wishlist_group_id')->references('id')->on('wishlist_groups')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->nullOnDelete();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->nullOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();

            $table->unique(['wishlist_group_id', 'vendor_listing_id'], 'uq_wg_vendor_listing');
            $table->unique(['wishlist_group_id', 'admin_product_listing_id'], 'uq_wg_admin_listing');

            $table->index(['customer_id', 'wishlist_group_id']);
            $table->index('vendor_listing_id');
            $table->index('admin_product_listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlist_groups');
    }
};
