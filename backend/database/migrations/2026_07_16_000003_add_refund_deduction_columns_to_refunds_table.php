<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('gateway_fee_deducted_cents')->default(0);
            $table->unsignedBigInteger('tax_deducted_cents')->default(0);
            $table->unsignedBigInteger('net_refund_cents')
                ->virtualAs('amount - gateway_fee_deducted_cents - tax_deducted_cents')
                ->comment('Actual amount returned to customer after deductions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_fee_deducted_cents',
                'tax_deducted_cents',
                'net_refund_cents',
            ]);
        });
    }
};
