<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-12 task 2/3: marketer_campaign_conversions gets an
 * explicit lifecycle status instead of only the boolean `commissioned`
 * flag (which only distinguished "paid" from "not yet paid" and could not
 * represent "reversed"). `commissioned`/`paid_at` are left in place — they
 * still drive payout inclusion (MarketerCampaignService::markConversionsPaid)
 * — this migration adds the missing states around them.
 *
 * Backfill (existing rows, before the column had a meaning):
 *   - commissioned = true  -> status = 'paid'
 *   - commissioned = false -> status = 'pending' (safe default: nothing was
 *     approved/credited yet under the old code, since approval didn't exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'reversed', 'paid'])
                ->default('pending')->after('commissioned');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->timestamp('reversed_at')->nullable()->after('approved_at');
            // Marks when this conversion's commission was credited to the
            // marketer wallet's pending_balance (approval time) and when it
            // was released from pending_balance into the spendable balance
            // (after the payout-clearing window) — see
            // ReleaseMarketerPendingCommissionJob.
            $table->timestamp('wallet_credited_at')->nullable()->after('reversed_at');
            $table->timestamp('wallet_released_at')->nullable()->after('wallet_credited_at');
        });

        DB::table('marketer_campaign_conversions')->where('commissioned', true)->update(['status' => 'paid']);
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_at', 'reversed_at', 'wallet_credited_at', 'wallet_released_at']);
        });
    }
};
