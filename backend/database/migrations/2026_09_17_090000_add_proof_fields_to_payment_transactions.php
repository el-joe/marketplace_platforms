<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank transfer has no automated verification (BankTransferGateway::verify()
 * always returns pending); admin's confirmBankTransfer() had nothing to
 * review against. Adds a place for the customer's uploaded receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('proof_file_path')->nullable()->after('raw_response');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['proof_file_path', 'proof_uploaded_at']);
        });
    }
};
