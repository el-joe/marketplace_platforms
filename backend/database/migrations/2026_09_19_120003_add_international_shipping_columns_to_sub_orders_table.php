<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plans/international_product_shipping.md Phase 1.
 *
 * All nullable / additive — zero behavior change for domestic orders. A
 * sub-order is "international" when origin_country_id !== orders.country_id
 * (computed in a model accessor, no generated column). fx_rate_* null means
 * domestic, no conversion happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->char('origin_country_id', 36)->nullable()->after('warehouse_id');
            $table->bigInteger('fx_rate_numerator')->nullable()->after('gateway_fee_rate');
            $table->bigInteger('fx_rate_denominator')->nullable()->after('fx_rate_numerator');
            $table->timestamp('fx_rate_captured_at')->nullable()->after('fx_rate_denominator');

            $table->foreign('origin_country_id')->references('id')->on('countries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropForeign(['origin_country_id']);
            $table->dropColumn([
                'origin_country_id',
                'fx_rate_numerator',
                'fx_rate_denominator',
                'fx_rate_captured_at',
            ]);
        });
    }
};
