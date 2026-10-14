<?php

namespace App\Services;

use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P7: cancellation/refund symmetry for ACCEPTED influencer invitations (the only invitations that are ever charged).
 *
 *  - fee still `pending` (never collected)  -> voided (status `waived`, platform_fee_voided_at): no wallet movement,
 *    it can no longer be collected by the wallet collector, the weekly statement or "mark as paid".
 *  - fee `paid` (wallet OR weekly statement) -> the vendor gets platformFeeTotal() (fee + VAT + marketing) back as a
 *    wallet credit (source_type REFUND_SOURCE). A statement is an immutable, already-issued document, so a statement
 *    paid fee is refunded through the wallet as well, never by editing the statement. Status becomes `refunded`.
 *  - the marketer's fee-share credit is reversed AFTER the vendor refund: wallet debit when the balance covers it,
 *    otherwise (short / frozen wallet) the amount is recorded as an outstanding clawback on the invitation, shown in
 *    the marketer finance pages and recovered by the hourly retry. A short marketer wallet never blocks the refund.
 *
 * Idempotent through platform_fee_refunded_at / platform_fee_voided_at / marketer_credit_reversed_at, each checked and
 * set under the invitation row lock in the same transaction as the wallet movement. Never throws.
 */
class MarketerInvitationRefundService
{
    public const REFUND_SOURCE = 'marketer_campaign_invitation_platform_fee_refund';

    public const REVERSAL_SOURCE = 'marketer_campaign_fee_share_reversal';

    public function __construct(private readonly WalletService $wallets) {}

    /** Refund/void every charged invitation of a cancelled or rejected campaign. @return int invitations changed */
    public function refundCampaign(MarketerCampaign|string $campaign, string $reason = 'campaign_cancelled'): int
    {
        $campaignId = $campaign instanceof MarketerCampaign ? $campaign->id : $campaign;
        $changed = 0;

        MarketerCampaignInvitation::where('campaign_id', $campaignId)
            ->where('status', 'accepted')
            ->where('platform_fee_payer', 'vendor')
            ->where('platform_fee_amount', '>', 0)
            ->get()
            ->each(function (MarketerCampaignInvitation $invitation) use ($reason, &$changed) {
                if ($this->refundInvitation($invitation, $reason)) {
                    $changed++;
                }
            });

        return $changed;
    }

    /** @return bool true when this call refunded or voided the vendor fee */
    public function refundInvitation(MarketerCampaignInvitation $invitation, string $reason = 'campaign_cancelled'): bool
    {
        $done = false;

        try {
            $done = DB::transaction(function () use ($invitation, $reason) {
                $locked = MarketerCampaignInvitation::whereKey($invitation->id)->lockForUpdate()->first();

                if (! $locked
                    || $locked->platform_fee_payer !== 'vendor'
                    || $locked->platform_fee_refunded_at !== null
                    || $locked->platform_fee_voided_at !== null) {
                    return false;
                }

                if ($locked->platform_fee_status === 'pending') {
                    $locked->update([
                        'platform_fee_status' => 'waived',
                        'platform_fee_voided_at' => now(),
                        'platform_fee_refund_reason' => $reason,
                    ]);
                    $invitation->setRawAttributes($locked->getAttributes(), true);

                    return true;
                }

                if ($locked->platform_fee_status !== 'paid') {
                    return false;
                }

                $amount = $locked->platformFeeTotal();
                if ($amount <= 0 || ! $locked->campaign?->vendor_id) {
                    return false;
                }

                $wallet = $this->wallets->getOrCreateWallet('vendor', $locked->campaign->vendor_id, $locked->platform_fee_currency ?? $locked->campaign->currency);
                $transaction = $this->wallets->credit(
                    $wallet,
                    $amount,
                    self::REFUND_SOURCE,
                    $locked->id,
                    'Refund of influencer platform fee + VAT + marketing fees (campaign cancelled)'
                );

                $locked->update([
                    'platform_fee_status' => 'refunded',
                    'platform_fee_refunded_at' => now(),
                    'platform_fee_refund_amount' => $amount,
                    'platform_fee_refund_wallet_tx_id' => $transaction->id,
                    'platform_fee_refund_reason' => $reason,
                ]);
                $invitation->setRawAttributes($locked->getAttributes(), true);

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('Influencer fee refund deferred; will be retried.', ['invitation_id' => $invitation->id, 'error' => $e->getMessage()]);
        }

        // The marketer side runs whenever the vendor side is settled (now or earlier), even if this call changed nothing.
        $invitation->refresh();
        if ($invitation->platform_fee_refunded_at !== null || $invitation->platform_fee_voided_at !== null) {
            $this->reverseMarketerCredit($invitation);
        }

        return $done;
    }

    /**
     * Takes the fee share back from the marketer. @return bool true when this call changed the marketer state
     * (reversed the credit, voided a never-made credit, or recorded the outstanding clawback).
     */
    public function reverseMarketerCredit(MarketerCampaignInvitation $invitation): bool
    {
        try {
            return DB::transaction(function () use ($invitation) {
                $locked = MarketerCampaignInvitation::whereKey($invitation->id)->lockForUpdate()->first();

                if (! $locked || $locked->marketer_credit_reversed_at !== null
                    || ($locked->platform_fee_refunded_at === null && $locked->platform_fee_voided_at === null)) {
                    return false;
                }

                $payout = (float) $locked->marketer_payout_amount;

                // Never credited (unpaid fee, or credit still deferred): nothing to take back, and the credit
                // service refuses to credit a cancelled campaign from now on.
                if ($locked->marketer_credited_at === null || $payout <= 0) {
                    $locked->update(['marketer_credit_reversed_at' => now(), 'marketer_clawback_amount' => 0]);
                    $invitation->setRawAttributes($locked->getAttributes(), true);

                    return true;
                }

                $currency = $locked->platform_fee_currency ?? $locked->campaign->currency;
                $wallet = $this->wallets->getOrCreateWallet('marketer', $locked->marketer_id, $currency);

                try {
                    $transaction = $this->wallets->debit(
                        $wallet,
                        $payout,
                        self::REVERSAL_SOURCE,
                        $locked->id,
                        'Reversal of campaign fee share (campaign cancelled)'
                    );
                } catch (\Throwable $e) {
                    // Short or frozen wallet: record the clawback, never block the vendor refund (already done).
                    $changed = (float) $locked->marketer_clawback_amount !== $payout;
                    $locked->update(['marketer_clawback_amount' => $payout]);
                    $invitation->setRawAttributes($locked->getAttributes(), true);

                    return $changed;
                }

                $locked->update([
                    'marketer_credit_reversed_at' => now(),
                    'marketer_credit_reversal_wallet_tx_id' => $transaction->id,
                    'marketer_clawback_amount' => 0,
                ]);
                $invitation->setRawAttributes($locked->getAttributes(), true);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Marketer fee-share reversal failed; will be retried.', ['invitation_id' => $invitation->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Hourly safety net: refunds owed on cancelled/rejected campaigns that failed earlier (frozen vendor wallet) and
     * outstanding marketer clawbacks. @return array{0: int, 1: int} [vendor refunds, marketer reversals]
     */
    public function runSafetyNet(): array
    {
        $refunds = 0;
        $reversals = 0;

        MarketerCampaignInvitation::query()
            ->where('status', 'accepted')
            ->where('platform_fee_payer', 'vendor')
            ->where('platform_fee_status', 'paid')
            ->whereNull('platform_fee_refunded_at')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['cancelled', 'rejected']))
            ->chunkById(100, function ($invitations) use (&$refunds) {
                foreach ($invitations as $invitation) {
                    if ($this->refundInvitation($invitation, 'campaign_cancelled')) {
                        $refunds++;
                    }
                }
            });

        MarketerCampaignInvitation::query()
            ->whereNull('marketer_credit_reversed_at')
            ->where('marketer_clawback_amount', '>', 0)
            ->chunkById(100, function ($invitations) use (&$reversals) {
                foreach ($invitations as $invitation) {
                    $before = (float) $invitation->marketer_clawback_amount;
                    $this->reverseMarketerCredit($invitation);
                    if ($invitation->marketer_credit_reversed_at !== null && $before > 0) {
                        $reversals++;
                    }
                }
            });

        return [$refunds, $reversals];
    }
}
