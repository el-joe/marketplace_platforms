<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid ad bookings settled via PaidAdPaymentMethod::Offline have no proof
 * tracking today (unlike payment_transactions for orders). Mirrors
 * 2026_09_17_090000_add_proof_fields_to_payment_transactions.php so an admin
 * marking a booking as offline-paid can attach the proof they collected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            $table->string('offline_proof_file_path')->nullable()->after('payment_transaction_id');
            $table->timestamp('offline_proof_uploaded_at')->nullable()->after('offline_proof_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            $table->dropColumn(['offline_proof_file_path', 'offline_proof_uploaded_at']);
        });
    }
};
