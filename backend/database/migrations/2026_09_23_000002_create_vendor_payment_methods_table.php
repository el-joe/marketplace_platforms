<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FBM (vendor-owned shipping) — client feature request section 4.
 *
 * Lets a vendor enable/disable which platform payment gateways their own
 * customers can use at checkout. Confirmed via exploration (see plan section
 * 4.2 note) that no equivalent per-vendor payment-method table existed —
 * payment methods were previously only configured platform-wide via
 * `payment_gateways` / `country_payment_gateways` (admin-managed, no vendor
 * scoping at all). This table is additive and does not replace those.
 *
 * In practice this is only meaningful/editable for FBM vendors (see the
 * `fulfillment_model === fbm` gate applied in the vendor portal settings
 * controller) — vendors on other fulfillment models don't own the shipping
 * relationship so the platform's default gateway set applies to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('payment_gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'payment_gateway_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_methods');
    }
};
