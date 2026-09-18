<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plans/international_product_shipping.md Phase 1, design decision #6.
 *
 * Extend, don't replace: an international shipment's customs/linehaul legs
 * may run under a different carrier's tracking number than the primary
 * shipments.tracking_number. Both columns nullable/additive — domestic events
 * are unaffected. This table is already append-only by convention (never
 * updated in place).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_tracking_events', function (Blueprint $table) {
            $table->char('carrier_id', 36)->nullable()->after('shipment_id');
            $table->string('external_tracking_number', 100)->nullable()->after('carrier_id');

            $table->foreign('carrier_id')->references('id')->on('shipping_carriers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipment_tracking_events', function (Blueprint $table) {
            $table->dropForeign(['carrier_id']);
            $table->dropColumn(['carrier_id', 'external_tracking_number']);
        });
    }
};
