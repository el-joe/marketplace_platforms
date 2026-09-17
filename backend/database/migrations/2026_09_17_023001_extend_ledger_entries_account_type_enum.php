<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-11 task 1: the ledger_entries.account_type enum is
 * missing several account types that LedgerService (and code that predates
 * this migration) already writes or needs to write:
 *
 *  - marketer_commission_payable: LedgerService::postOrderCapture() already
 *    posts this literal for vendor-campaign marketer commission, but it was
 *    never added to the enum — any order with a marketer commission > 0
 *    would fail this insert. This migration fixes that latent bug.
 *  - marketer_payable: the marketer's payable balance once a payout is due
 *    (MarketerPayoutPaid event), distinct from the payment-time liability
 *    above.
 *  - carrier_payable: amount owed to the delivery carrier for shipping.
 *  - warranty_revenue: platform warranty premium revenue, broken out of the
 *    shipping_revenue residual (see LedgerService::postOrderCapture()).
 *  - coupon_expense: platform-funded coupon cost.
 *  - shipping_subsidy_expense: admin-absorbed exceptional-zone shipping gap.
 *  - wallet_liability: customer/vendor/marketer wallet balances the
 *    platform owes back.
 *
 * Backfill-safe: this only widens the enum. Existing rows keep their
 * current account_type value untouched; MODIFY COLUMN on an enum in MySQL
 * does not rewrite existing values as long as they remain valid members.
 */
return new class extends Migration
{
    private const OLD_ENUM = "enum('customer_payment','platform_revenue','platform_commission','seller_payable','gateway_fee','tax_payable','refund_liability','shipping_revenue','cod_clearing')";

    private const NEW_ENUM = "enum('customer_payment','platform_revenue','platform_commission','seller_payable','gateway_fee','tax_payable','refund_liability','shipping_revenue','cod_clearing','marketer_commission_payable','marketer_payable','carrier_payable','warranty_revenue','coupon_expense','shipping_subsidy_expense','wallet_liability')";

    public function up(): void
    {
        DB::statement('ALTER TABLE ledger_entries MODIFY account_type '.self::NEW_ENUM.' NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledger_entries MODIFY account_type '.self::OLD_ENUM.' NOT NULL');
    }
};
