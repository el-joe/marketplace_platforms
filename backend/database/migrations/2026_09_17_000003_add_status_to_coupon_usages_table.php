<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-04 task 2: coupon usage now has a reserve/consume/release
 * lifecycle instead of being written once and never reverted.
 *
 * Backfill-safe: existing coupon_usages rows were created (by the old
 * recordUsage() call) only for orders that had already been placed, i.e.
 * usages that really happened — so they backfill to 'consumed' via the
 * column default, not 'reserved'. New rows explicitly set 'reserved' at
 * placement time and are transitioned by CouponUsageService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->enum('status', ['reserved', 'consumed', 'released'])
                ->default('consumed')
                ->after('discount_amount')
                ->comment('reserved at placement, consumed on payment capture/COD delivery, released on any rollback path (decrements coupons.times_used).');
            $table->index(['coupon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
