<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plans/international_product_shipping.md Phase 1.
 *
 * Direct origin-country -> destination-country shipping rate rows. Deliberately
 * NOT the existing shipping_zones/shipping_rates machinery (that's intra-country
 * warehouse-dispatch routing, zone -> zone). Column names mirror shipping_rates
 * for consistency (base_fee, rate_per_kg). carrier_id nullable = generic
 * fallback rate for the corridor when no carrier-specific row exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('international_shipping_rates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('origin_country_id', 36);
            $table->char('destination_country_id', 36);
            $table->char('carrier_id', 36)->nullable();
            $table->bigInteger('base_fee');
            $table->bigInteger('rate_per_kg');
            $table->bigInteger('customs_fee_flat')->nullable();
            $table->smallInteger('min_eta_days');
            $table->smallInteger('max_eta_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('origin_country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->foreign('destination_country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->foreign('carrier_id')->references('id')->on('shipping_carriers')->nullOnDelete();

            $table->unique(
                ['origin_country_id', 'destination_country_id', 'carrier_id'],
                'intl_shipping_rates_corridor_carrier_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('international_shipping_rates');
    }
};
