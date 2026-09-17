<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalizing offline-payment proof upload (bank transfer today, any
 * PaymentGatewayFactory::isOffline() gateway going forward) to also accept
 * an optional customer note alongside the proof file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->text('note')->nullable()->after('proof_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['note']);
        });
    }
};
