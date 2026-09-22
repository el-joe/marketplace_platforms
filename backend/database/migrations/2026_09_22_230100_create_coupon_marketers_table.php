<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-marketer targeting for coupons (client feature request #3.1).
 *
 * When a coupon has rows here, it is restricted to those marketers'
 * listings; when it has none, it applies to everyone (default behavior).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_marketers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'marketer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_marketers');
    }
};
