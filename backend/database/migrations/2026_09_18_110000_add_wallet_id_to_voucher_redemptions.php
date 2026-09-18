<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WALLET_MERGE_PLAN.md Phase 2: `voucher_redemptions.customer_wallet_id`
 * points at the legacy `customer_wallets` table (ON DELETE SET NULL). Rather
 * than repurpose or drop that column — which would touch existing rows and
 * the FK — this adds an additive, nullable `wallet_id` column pointing at
 * the polymorphic `wallets` table, which `VoucherService::redeem()` now
 * populates going forward. `customer_wallet_id` is left in place untouched
 * for backward compat with historical rows/queries; it is no longer written
 * by the service layer after this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->uuid('wallet_id')->nullable()->after('customer_wallet_id');
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->index('wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
            $table->dropIndex(['wallet_id']);
            $table->dropColumn('wallet_id');
        });
    }
};
