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
         * Table: payment_transactions Every payment attempt — append-only. ColumnTypeDescriptionidUUID PKorder_idUUID FK → orders.iduser_idUUID FK → users.idtypeENUM('authorization','capture','sale','refund','void','chargeback')gatewayVARCHAR(50)gateway_transaction_idVARCHAR(255) UNIQUEFrom gatewayidempotency_keyVARCHAR(100) UNIQUEPrevents double processingamountBIGINTSigned: positive for charges, negative for refundscurrencyCHAR(3)gateway_feeBIGINT DEFAULT 0What gateway charged usstatusENUM('pending','succeeded','failed','cancelled')failure_codeVARCHAR(50) NULLfailure_messageTEXT NULLpayment_method_idUUID FK → payment_methods.id NULLraw_requestJSONB NULLFor debuggingraw_responseJSONB NULLprocessed_atTIMESTAMP NULLcreated_atTIMESTAMPNo updated_at
         */
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('customer_id')->index();
            $table->enum('type', ['authorization', 'capture', 'sale', 'refund', 'void', 'chargeback']);
            $table->string('gateway', 50);
            $table->string('gateway_transaction_id', 255)->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->bigInteger('gateway_fee')->default(0);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'cancelled']);
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_message')->nullable();
            $table->uuid('payment_method_id')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
