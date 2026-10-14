<?php

namespace App\Services;

use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credits an influencer's fee share (invitation.marketer_payout_amount = fee x share %) to the marketer wallet
 * once the vendor's charge for that invitation is `paid`. Called from every paid path (wallet, weekly
 * statement, admin mark-paid) and from the retry job. Exactly once: the invitation row is locked and
 * marketer_credited_at / marketer_payout_wallet_tx_id are checked and set in the same transaction as the credit.
 * Never throws: a frozen wallet (or any failure) is logged and leaves marketer_credited_at null for the retry job.
 */
class MarketerFeePayoutService
{
    public const SOURCE_TYPE = 'marketer_campaign_fee_share';

    public function __construct(private readonly WalletService $wallets) {}

    /** @return bool true when this call credited the wallet */
    public function creditForInvitation(MarketerCampaignInvitation $invitation): bool
    {
        try {
            return DB::transaction(function () use ($invitation) {
                $locked = MarketerCampaignInvitation::whereKey($invitation->id)->lockForUpdate()->first();

                if (! $locked
                    || $locked->platform_fee_status !== 'paid'
                    || $locked->platform_fee_payer !== 'vendor'
                    || $locked->marketer_credited_at !== null
                    || $locked->marketer_payout_wallet_tx_id !== null
                    || $locked->marketer_credit_reversed_at !== null
                    || in_array($locked->campaign?->status, ['cancelled', 'rejected'], true)
                    || (float) $locked->marketer_payout_amount <= 0
                    || ! $locked->marketer?->isInfluencer()) {
                    return false;
                }

                $currency = $locked->platform_fee_currency ?? $locked->campaign->currency;
                $wallet = $this->wallets->getOrCreateWallet('marketer', $locked->marketer_id, $currency);

                if ($wallet->is_frozen) {
                    Log::warning('Marketer fee-share credit deferred: wallet is frozen.', [
                        'invitation_id' => $locked->id,
                        'marketer_id' => $locked->marketer_id,
                    ]);

                    return false;
                }

                $transaction = $this->wallets->credit(
                    $wallet,
                    $locked->marketer_payout_amount,
                    self::SOURCE_TYPE,
                    $locked->id,
                    'Campaign fee share ('.rtrim(rtrim(number_format((float) $locked->marketer_fee_share_pct, 4, '.', ''), '0'), '.').'%) for accepted campaign invitation'
                );

                $locked->update([
                    'marketer_credited_at' => now(),
                    'marketer_payout_wallet_tx_id' => $transaction->id,
                ]);
                $invitation->setRawAttributes($locked->getAttributes(), true);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Marketer fee-share credit failed; will be retried.', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
