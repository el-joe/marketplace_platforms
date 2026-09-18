<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plans/international_product_shipping.md Phase 1.
 *
 * Append-only currency conversion snapshots — never updated in place, only
 * inserted, matching inventory_movements' established append-only convention
 * in this codebase. Rate stored as an exact integer fraction
 * (rate_numerator / rate_denominator), never a float or plain decimal, so
 * conversions stay integer-only math (see CurrencyConversionService, Phase 2).
 * Most recent effective_at row for a currency pair wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('from_currency_code', 3);
            $table->char('to_currency_code', 3);
            $table->bigInteger('rate_numerator');
            $table->bigInteger('rate_denominator');
            $table->timestamp('effective_at');
            $table->timestamp('created_at')->nullable();

            $table->index(
                ['from_currency_code', 'to_currency_code', 'effective_at'],
                'currency_exchange_rates_pair_effective_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
