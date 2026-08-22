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
         * Table: ledger_entries Double-entry bookkeeping. Append-only. Every financial event creates balanced debit + credit entries. ColumnTypeDescriptionidUUID PKtransaction_group_idUUIDGroups balanced entries (must sum to zero)account_typeENUM('customer_payment','platform_revenue','platform_commission','seller_payable','gateway_fee','tax_payable','refund_liability','shipping_revenue','cod_clearing')account_holder_typeVARCHAR(50) NULLseller_profiles, usersaccount_holder_idUUID NULLdebit_centsBIGINT DEFAULT 0One of debit/credit is zerocredit_centsBIGINT DEFAULT 0currencyCHAR(3)reference_typeVARCHAR(50)order, refund, payoutreference_idUUIDdescriptionTEXTcreated_atTIMESTAMP
         */
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_group_id')->index();
            $table->enum('account_type', ['customer_payment', 'platform_revenue', 'platform_commission', 'seller_payable', 'gateway_fee', 'tax_payable', 'refund_liability', 'shipping_revenue', 'cod_clearing']);
            $table->string('account_holder_type', 50)->nullable();
            $table->uuid('account_holder_id')->nullable();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->char('currency', 3);
            $table->string('reference_type', 50);
            $table->uuid('reference_id');
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
