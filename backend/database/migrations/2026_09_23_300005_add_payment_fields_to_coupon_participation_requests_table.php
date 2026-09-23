<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_participation_requests', function (Blueprint $table) {
            $table->enum('payment_method', ['wallet', 'bank_transfer'])->default('wallet')->after('offered_fee_amount');
            $table->string('bank_transfer_proof_path')->nullable()->after('payment_method');
            $table->uuid('reviewed_by_admin_id')->nullable()->after('paid_at');
            $table->foreign('reviewed_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupon_participation_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_admin_id']);
            $table->dropColumn(['payment_method', 'bank_transfer_proof_path', 'reviewed_by_admin_id']);
        });
    }
};
