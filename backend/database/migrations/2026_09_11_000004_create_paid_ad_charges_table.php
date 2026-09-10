<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_charges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->char('paid_ad_booking_id', 36);
            $t->enum('advertiser_type', ['vendor', 'marketer']);
            $t->char('vendor_id', 36)->nullable();
            $t->char('marketer_id', 36)->nullable();
            $t->char('country_id', 36);
            $t->char('currency', 3);
            $t->enum('type', ['fixed', 'cpm', 'cpc', 'budget_reserve', 'refund', 'adjustment']);
            $t->bigInteger('amount')->comment('Positive = charge to advertiser, negative = refund. Base currency.');
            $t->bigInteger('tax_amount')->default(0);
            $t->enum('settlement', ['wallet', 'payout_deduction', 'offline']);
            $t->char('wallet_transaction_id', 36)->nullable();
            $t->unsignedBigInteger('payout_id')->nullable()->comment('payouts.id is bigint');
            $t->timestamp('settled_at')->nullable();
            $t->string('note', 255)->nullable();
            $t->char('created_by_admin_id', 36)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->foreign('paid_ad_booking_id')->references('id')->on('paid_ad_bookings')->restrictOnDelete();
            $t->foreign('payout_id')->references('id')->on('payouts')->nullOnDelete();
            $t->index(['vendor_id', 'settlement', 'payout_id', 'currency'], 'pac_vendor_settle_idx');
            $t->index(['paid_ad_booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_charges');
    }
};
