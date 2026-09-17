<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-09 task 5: warranty_claims needs
 *  - a nullable FK to warranty_purchases (a claim can be a platform-warranty
 *    claim tied to a purchase, or a brand-only claim with none),
 *  - a claim_type enum('brand','platform') so brand-warranty-only claims are
 *    representable (the old code crashed on
 *    `$orderItem->warrantyPurchase->coverage_ends_at` because it assumed
 *    every claim has a platform warranty),
 *  - 'marketer_listing' added to the listing_type enum so a marketer-listing
 *    order item can be claimed.
 *
 * Backfill-safe: existing rows get claim_type derived from
 * covered_by_platform_warranty, warranty_purchase_id is left null for them
 * (no back-reference existed before), and the enum widening is additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->uuid('warranty_purchase_id')->nullable()->after('order_item_id');
            $table->string('claim_type', 20)->nullable()->after('listing_type');
        });

        DB::statement("ALTER TABLE warranty_claims MODIFY listing_type ENUM('vendor_listing','admin_listing','marketer_listing') NOT NULL");

        DB::table('warranty_claims')->update([
            'claim_type' => DB::raw("CASE WHEN covered_by_platform_warranty = 1 THEN 'platform' ELSE 'brand' END"),
        ]);

        DB::statement("ALTER TABLE warranty_claims MODIFY claim_type VARCHAR(20) NOT NULL DEFAULT 'brand'");

        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->foreign('warranty_purchase_id')->references('id')->on('warranty_purchases')->nullOnDelete();
            $table->index('warranty_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropForeign(['warranty_purchase_id']);
            $table->dropColumn(['warranty_purchase_id', 'claim_type']);
        });

        DB::statement("ALTER TABLE warranty_claims MODIFY listing_type ENUM('vendor_listing','admin_listing') NOT NULL");
    }
};
