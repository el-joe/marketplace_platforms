<?php

namespace App\Services\Marketer;

use App\Exceptions\InsufficientBalanceException;
use App\Models\MarketerCampaign;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Collection of the campaign fee (fixed + variable marketing fee, snapshotted on the campaign at creation).
 *
 * Charged once, when the campaign goes live: from the owner's wallet (vendor or travel agency) when it covers the
 * fee, otherwise the campaign keeps campaign_fee_status = pending. A vendor's pending fee is carried by the next
 * payout statement as a `campaign_fee` line (GenerateVendorPayoutsJob); a travel agency has no payout statements,
 * so its pending fee is retried against the agency wallet every night (retryPendingAgencyFees(), run by
 * CompleteTravelBookingsJob). A fee that is still pending when the campaign is rejected or cancelled is voided;
 * a paid fee is not refunded.
 */
class CampaignFeeService
{
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOIDED = 'voided';

    public const SOURCE_WALLET = 'marketer_campaign_fee';

    public function __construct(private readonly WalletService $wallets) {}

    /** The status a freshly created campaign starts in (owner funded = vendor or travel agency). */
    public function initialStatus(float $fee, bool $ownerFunded): string
    {
        return $ownerFunded && $fee > 0 ? self::STATUS_PENDING : self::STATUS_NOT_APPLICABLE;
    }

    /**
     * The wallet that pays the fee: [owner_type, owner_id], or null for platform / marketer campaigns.
     *
     * @return array{0: string, 1: string}|null
     */
    public function walletOwner(MarketerCampaign $campaign): ?array
    {
        if ($campaign->vendor_id) {
            return ['vendor', (string) $campaign->vendor_id];
        }
        if ($campaign->owner_type === MarketerCampaign::OWNER_TRAVEL_AGENCY && $campaign->owner_id) {
            return ['travel_agency', (string) $campaign->owner_id];
        }

        return null;
    }

    /**
     * Charge the fee from the vendor wallet if it covers it.
     *
     * @return bool true when the fee is now paid
     */
    public function charge(MarketerCampaign $campaign): bool
    {
        $owner = $this->walletOwner($campaign);
        if ($campaign->campaign_fee_status !== self::STATUS_PENDING || ! $owner) {
            return false;
        }

        $fee = round((float) $campaign->marketing_fee_total, 2);
        if ($fee <= 0) {
            return false;
        }

        try {
            return DB::transaction(function () use ($campaign, $fee, $owner) {
                $locked = MarketerCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                if ($locked->campaign_fee_status !== self::STATUS_PENDING || $locked->payoutItems()->exists()) {
                    return false;
                }

                $wallet = $this->wallets->getOrCreateWallet($owner[0], $owner[1], (string) $locked->currency);
                $this->wallets->debit(
                    $wallet,
                    $fee,
                    self::SOURCE_WALLET,
                    (string) $locked->id,
                    mb_substr(__('marketer_campaigns.wallet.campaign_fee', ['campaign' => $locked->getPromotedTitle()]), 0, 255)
                );

                $locked->update([
                    'campaign_fee_status' => self::STATUS_PAID,
                    'campaign_fee_paid_at' => now(),
                    'campaign_fee_paid_via' => 'wallet',
                ]);
                $campaign->setRawAttributes($locked->getAttributes(), true);

                return true;
            });
        } catch (InsufficientBalanceException) {
            return false;
        }
    }

    /**
     * Travel agencies pay by wallet only: try again for every live campaign whose fee is still pending.
     *
     * @return int campaigns whose fee was collected
     */
    public function retryPendingAgencyFees(): int
    {
        $collected = 0;

        MarketerCampaign::where('owner_type', MarketerCampaign::OWNER_TRAVEL_AGENCY)
            ->where('campaign_fee_status', self::STATUS_PENDING)
            ->whereIn('status', ['active', 'auto_approved', 'paused'])
            ->each(function (MarketerCampaign $campaign) use (&$collected) {
                if ($this->charge($campaign)) {
                    $collected++;
                }
            });

        return $collected;
    }

    /** Cancel/reject: an unpaid fee is never charged. */
    public function void(MarketerCampaign $campaign): void
    {
        if ($campaign->campaign_fee_status === self::STATUS_PENDING) {
            $campaign->update(['campaign_fee_status' => self::STATUS_VOIDED]);
        }
    }

    /** Vendor payout statement settled the fee. */
    public function markPaidViaStatement(MarketerCampaign $campaign): void
    {
        $campaign->update([
            'campaign_fee_status' => self::STATUS_PAID,
            'campaign_fee_paid_at' => now(),
            'campaign_fee_paid_via' => 'payout',
        ]);
    }
}
