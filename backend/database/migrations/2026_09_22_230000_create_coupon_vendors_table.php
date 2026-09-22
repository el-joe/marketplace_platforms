<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-vendor targeting for coupons (client feature request #3.1).
 *
 * The legacy `coupons.vendor_id` column is kept as-is for backward
 * compatibility (single-vendor coupons created before this table existed).
 * When a coupon has rows here, it is restricted to those vendors; when it
 * has none, it applies to everyone (default/legacy behavior).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_vendors');
    }
};
