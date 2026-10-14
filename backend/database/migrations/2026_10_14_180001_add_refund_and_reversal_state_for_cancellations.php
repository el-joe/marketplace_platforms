<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P7 (cancellation/refund symmetry): per-invitation refund + marketer-credit reversal state (idempotency markers),
 * the new 'refunded' fee status, and a `kind` on affiliate fee settlements ('charge' | 'refund') so a refund after
 * settlement is a history row of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->timestamp('platform_fee_refunded_at')->nullable();
            $table->decimal('platform_fee_refund_amount', 19, 4)->nullable();
            $table->char('platform_fee_refund_wallet_tx_id', 36)->nullable();
            $table->timestamp('platform_fee_voided_at')->nullable();
            $table->string('platform_fee_refund_reason', 30)->nullable();
            $table->timestamp('marketer_credit_reversed_at')->nullable();
            $table->char('marketer_credit_reversal_wallet_tx_id', 36)->nullable();
            $table->decimal('marketer_clawback_amount', 19, 4)->default(0);
        });

        Schema::table('marketer_campaign_fee_settlements', function (Blueprint $table) {
            $table->string('kind', 10)->default('charge');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `marketer_campaign_invitations` MODIFY `platform_fee_status` ENUM('not_applicable','pending','paid','waived','refunded') NOT NULL DEFAULT 'not_applicable' COMMENT 'not_applicable = affiliate type (always free); refunded = paid fee given back to the vendor'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE `marketer_campaign_invitations` SET `platform_fee_status` = 'paid' WHERE `platform_fee_status` = 'refunded'");
            DB::statement("ALTER TABLE `marketer_campaign_invitations` MODIFY `platform_fee_status` ENUM('not_applicable','pending','paid','waived') NOT NULL DEFAULT 'not_applicable' COMMENT 'not_applicable = affiliate type (always free)'");
        }

        Schema::table('marketer_campaign_fee_settlements', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropColumn([
                'platform_fee_refunded_at', 'platform_fee_refund_amount', 'platform_fee_refund_wallet_tx_id',
                'platform_fee_voided_at', 'platform_fee_refund_reason', 'marketer_credit_reversed_at',
                'marketer_credit_reversal_wallet_tx_id', 'marketer_clawback_amount',
            ]);
        });
    }
};
