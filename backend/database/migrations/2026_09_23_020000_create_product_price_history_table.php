<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client feature request doc, section 6 ("تثبيت أول سعر للمنتج / Price History").
 *
 * Every price ever set on a vendor listing (the first one at creation, incl.
 * drafts, and every subsequent change) is logged here so admins can look
 * back at a listing's price history indefinitely. `price` is BIGINT
 * base-currency units to match vendor_listings.price's convention (see
 * 2026_09_17_000002_convert_vendor_listings_prices_to_bigint.php) rather
 * than DECIMAL — every other money column in this schema is BIGINT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_listing_id');
            $table->bigInteger('price');
            $table->timestamp('recorded_at');
            $table->enum('source', ['initial', 'update']);
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
            $table->index(['vendor_listing_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
    }
};
