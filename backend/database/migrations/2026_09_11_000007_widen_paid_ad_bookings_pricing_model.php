<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * paid_ad_bookings.pricing_model was left as varchar(10), which truncates
     * 'fixed_weekly' (12 chars) and 'fixed_monthly' (13 chars) — the two longest
     * PaidAdSlotPricingModel values. Widen it to match paid_ad_slots.pricing_model.
     */
    public function up(): void
    {
        Schema::table('paid_ad_bookings', function (Blueprint $t) {
            $t->string('pricing_model', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('paid_ad_bookings', function (Blueprint $t) {
            $t->string('pricing_model', 10)->change();
        });
    }
};
