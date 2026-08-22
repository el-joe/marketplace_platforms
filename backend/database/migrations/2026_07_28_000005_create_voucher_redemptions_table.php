<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('voucher_id', 36)->index();
            $table->char('customer_id', 36)->index();

            // The customer_wallets row that was credited
            $table->char('customer_wallet_id', 36)->nullable();

            // Amount credited (= voucher.amount at redemption time) — BIGINT base-currency
            $table->unsignedBigInteger('amount')
                  ->comment('BIGINT base-currency units credited. No /100.');
            $table->char('currency_code', 3);

            // Wallet balance after this credit — for audit trail
            $table->unsignedBigInteger('wallet_balance_after');

            $table->timestamp('redeemed_at')->useCurrent();
            // NO updated_at — append-only table

            $table->index(['voucher_id', 'customer_id']);

            $table->foreign('voucher_id')
                  ->references('id')->on('vouchers')->onDelete('restrict');
            $table->foreign('customer_id')
                  ->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('customer_wallet_id')
                  ->references('id')->on('customer_wallets')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
