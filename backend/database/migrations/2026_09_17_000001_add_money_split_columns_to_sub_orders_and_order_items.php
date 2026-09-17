<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-03 task 2: store the vendor/platform/marketer money split
 * produced by CheckoutPricingEngine instead of recalculating it later.
 * Backfill-safe: new columns default to 0 so existing populated rows stay
 * valid without a separate backfill pass (their historical split is simply
 * unknown/zero, which is correct — the old code never computed it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->bigInteger('vendor_coupon_cost')->default(0)->after('platform_commission')
                ->comment('Vendor-funded share of the coupon discount on this sub-order (funded_by vendor=100%, shared=vendor_share_pct, platform=0). BIGINT base currency.');
            $table->bigInteger('platform_coupon_cost')->default(0)->after('vendor_coupon_cost')
                ->comment('Platform-funded share of the coupon discount on this sub-order. BIGINT base currency.');
            $table->bigInteger('marketer_commission')->default(0)->after('platform_coupon_cost')
                ->comment('Marketer commission owed for lines in this sub-order (D5: owner is vendor or platform, see marketer_commission_owner). BIGINT base currency.');
            $table->enum('marketer_commission_owner', ['vendor', 'platform'])->nullable()->after('marketer_commission')
                ->comment('Who pays marketer_commission for this sub-order: the vendor (vendor-listing campaign) or the platform (admin-listing campaign).');
            $table->bigInteger('warranty_revenue')->default(0)->after('marketer_commission_owner')
                ->comment('Platform revenue from warranty plans sold on this sub-order\'s lines (platform underwrites 100% of warranty_total). BIGINT base currency.');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->bigInteger('vendor_coupon_cost')->default(0)->after('line_discount')
                ->comment('Vendor-funded share of this line\'s coupon discount. BIGINT base currency.');
            $table->bigInteger('marketer_commission')->default(0)->after('commission_amount')
                ->comment('Marketer commission for this line, if it was sold through a marketer listing. BIGINT base currency.');
            $table->bigInteger('platform_commission_after_discount')->default(0)->after('marketer_commission')
                ->comment('This line\'s share of the sub-order platform_commission after the vendor\'s commission_discount is applied (Sigma per order_item == sub_orders.platform_commission, by construction).');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['vendor_coupon_cost', 'marketer_commission', 'platform_commission_after_discount']);
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn(['vendor_coupon_cost', 'platform_coupon_cost', 'marketer_commission', 'marketer_commission_owner', 'warranty_revenue']);
        });
    }
};
