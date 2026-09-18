<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plans/international_product_shipping.md Phase 1.
 *
 * "Listing X can physically ship to destination country Y" — one row per
 * (listing, destination). vendor_listing_id / admin_listing_id nullable pair
 * replicates marketplace_shipping_rules's exact convention for attaching a
 * rule to either listing type: both columns nullable, no DB-level CHECK
 * constraint (marketplace_shipping_rules has none either — enforced at the
 * application layer, e.g. FbnController/AdminListingController always set
 * exactly one and null the other). We follow the same approach here rather
 * than inventing a CHECK constraint marketplace_shipping_rules doesn't have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('international_shipping_eligibility', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('vendor_listing_id', 36)->nullable();
            $table->char('admin_listing_id', 36)->nullable();
            $table->char('destination_country_id', 36);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
            $table->foreign('admin_listing_id')->references('id')->on('admin_listings')->cascadeOnDelete();
            $table->foreign('destination_country_id')->references('id')->on('countries')->restrictOnDelete();

            $table->index(['vendor_listing_id', 'destination_country_id']);
            $table->index(['admin_listing_id', 'destination_country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('international_shipping_eligibility');
    }
};
