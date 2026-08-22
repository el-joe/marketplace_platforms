<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Table: payouts ColumnTypeDescriptionidUUID PKpayout_numberVARCHAR(30) UNIQUEPO-2026-00001seller_idUUID FK → seller_profiles.idperiod_startDATEperiod_endDATEgross_sales_centsBIGINTcommission_centsBIGINTrefunds_deducted_centsBIGINTchargebacks_deducted_centsBIGINTstorage_fees_centsBIGINT DEFAULT 0FBN feesad_fees_centsBIGINT DEFAULT 0Sponsored ad spendother_adjustments_centsBIGINT DEFAULT 0net_amount_centsBIGINTFinal transfer amountcurrencyCHAR(3)statusENUM('pending','approved','processing','completed','failed','on_hold')bank_account_idUUID FK → seller_bank_accounts.idpayout_methodENUM('bank_transfer','wallet','paypal')gateway_referenceVARCHAR(255) NULLBank txn IDapproved_by_admin_idUUID FK → users.id NULLprocessed_atTIMESTAMP NULLfailed_reasonTEXT NULLreceipt_urlVARCHAR(500) NULLPDF receiptcreated_at, updated_atTIMESTAMPS
         */
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_number', 30)->unique();
            $table->uuid('vendor_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('gross_sales');
            $table->bigInteger('commission');
            $table->bigInteger('refunds_deducted');
            $table->bigInteger('chargebacks_deducted');
            $table->bigInteger('storage_fees')->default(0);
            $table->bigInteger('ad_fees')->default(0);
            $table->bigInteger('other_adjustments')->default(0);
            $table->bigInteger('net_amount');
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'approved', 'processing', 'completed', 'failed', 'on_hold']);
            $table->uuid('bank_account_id')->index();
            $table->enum('payout_method', ['bank_transfer', 'wallet', 'paypal']);
            $table->string('gateway_reference', 255)->nullable();
            $table->uuid('approved_by_admin_id')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->string('receipt_url', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
