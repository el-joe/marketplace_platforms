<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-05 task 2 & 6.
 *
 * - orders.payment_gateway_code: the actual gateway used (thawani, paytabs,
 *   cod, wallet, bank_transfer), separate from orders.payment_method which
 *   must stay one of the enum's four/five generic buckets. Backfilled from
 *   the existing payment_method column so older rows (which incorrectly
 *   held the gateway code before this fix) keep a usable value.
 * - payment_transactions.gateway_amount/gateway_currency/exchange_rate:
 *   store the amount actually charged at the gateway (which can differ
 *   from the order's amount/currency when the gateway's configured
 *   currency differs), alongside the conversion rate used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_gateway_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_gateway_code', 50)->nullable()->after('payment_method');
            });

            DB::table('orders')->update(['payment_gateway_code' => DB::raw('payment_method')]);
        }

        if (! Schema::hasColumn('payment_transactions', 'gateway_amount')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->bigInteger('gateway_amount')->nullable()->after('amount');
                $table->char('gateway_currency', 3)->nullable()->after('currency');
                $table->decimal('exchange_rate', 18, 8)->nullable()->after('gateway_currency');
            });

            DB::table('payment_transactions')->update([
                'gateway_amount' => DB::raw('amount'),
                'gateway_currency' => DB::raw('currency'),
                'exchange_rate' => 1,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_gateway_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_gateway_code');
            });
        }

        if (Schema::hasColumn('payment_transactions', 'gateway_amount')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->dropColumn(['gateway_amount', 'gateway_currency', 'exchange_rate']);
            });
        }
    }
};
