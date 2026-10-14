<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignFeeAccrual;
use App\Models\MarketerCampaignFeeSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Debits the vendor the affiliate marketing fees accrued on a campaign (D4/D10: the affiliate COMMISSION keeps its
 * per-order vendor-payout deduction; only the marketing fees on it are charged here).
 *
 * settle() is incremental and idempotent: it takes every accrual row not yet on a settlement (accrued rows plus
 * reversal rows), nets them and, when the net is positive, creates ONE settlement and links the rows to it. Late
 * approvals/refunds after a settlement simply stay unsettled and are picked up by the next run (or at once when the
 * campaign is already closed, see settleIfClosed). A net <= 0 leaves the rows unsettled so a later accrual offsets it.
 * The settlement is paid from the vendor wallet when it covers it; otherwise it stays `pending` and the weekly
 * statement deducts it as an `affiliate_marketing_fee` line (PayoutCalculationService / GenerateVendorPayoutsJob).
 */
class AffiliateFeeSettlementService
{
    public const WALLET_SOURCE = 'marketer_campaign_affiliate_marketing_fee';

    public function __construct(private readonly WalletService $wallets = new WalletService) {}

    /** True when the campaign finished/cancelled or was auto-paused because its commission budget ran out. */
    public function isClosed(MarketerCampaign $campaign): bool
    {
        if (in_array($campaign->status, ['cancelled', 'rejected', 'done', 'completed'], true)) {
            return true;
        }

        return $campaign->status === 'paused'
            && (float) $campaign->max_commission_budget > 0
            && (float) $campaign->commission_budget_spent >= (float) $campaign->max_commission_budget;
    }

    /** Settle only when the campaign is closed. Never throws (called from approval / attribution flows). */
    public function settleIfClosed(MarketerCampaign|string $campaign, string $trigger = 'auto'): ?MarketerCampaignFeeSettlement
    {
        try {
            $campaign = $campaign instanceof MarketerCampaign ? $campaign->fresh() : MarketerCampaign::find($campaign);

            return $campaign && $this->isClosed($campaign) ? $this->settle($campaign, $trigger) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function settle(MarketerCampaign $campaign, string $trigger = 'manual'): ?MarketerCampaignFeeSettlement
    {
        $settlement = DB::transaction(function () use ($campaign, $trigger) {
            $locked = MarketerCampaign::whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->vendor_id) {
                return null;
            }

            $rows = MarketerCampaignFeeAccrual::where('campaign_id', $locked->id)
                ->whereNull('settlement_id')
                ->whereIn('status', [MarketerCampaignFeeAccrual::STATUS_ACCRUED, MarketerCampaignFeeAccrual::STATUS_REVERSED])
                ->lockForUpdate()
                ->get();

            $amount = round((float) $rows->sum(fn ($r) => (float) $r->marketing_total_delta), 2);
            if ($rows->isEmpty() || $amount <= 0) {
                return null;
            }

            $settlement = MarketerCampaignFeeSettlement::create([
                'campaign_id' => $locked->id,
                'vendor_id' => $locked->vendor_id,
                'currency' => $locked->currency,
                'amount' => $amount,
                'status' => MarketerCampaignFeeSettlement::STATUS_PENDING,
                'trigger' => $trigger,
            ]);

            foreach ($rows as $row) {
                $row->update([
                    'settlement_id' => $settlement->id,
                    'settled_at' => now(),
                    'status' => $row->status === MarketerCampaignFeeAccrual::STATUS_ACCRUED
                        ? MarketerCampaignFeeAccrual::STATUS_SETTLED
                        : $row->status,
                ]);
            }

            $locked->update(['affiliate_fee_settled_at' => now()]);

            return $settlement;
        });

        if ($settlement) {
            $this->collectFromWallet($settlement);
        }

        return $settlement?->fresh();
    }

    /**
     * Wallet-first collection of a pending settlement (also the retry used by the hourly job). The settlement row
     * is locked and re-read, so a concurrent statement run can never charge it twice: the loser sees `paid` or an
     * existing statement line and does nothing.
     */
    public function collectFromWallet(MarketerCampaignFeeSettlement $settlement): bool
    {
        try {
            return DB::transaction(function () use ($settlement) {
                $locked = MarketerCampaignFeeSettlement::whereKey($settlement->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== MarketerCampaignFeeSettlement::STATUS_PENDING || $locked->payoutItems()->exists()) {
                    return false;
                }

                $wallet = $this->wallets->getOrCreateWallet('vendor', $locked->vendor_id, $locked->currency);
                $this->wallets->debit(
                    $wallet,
                    $locked->amount,
                    self::WALLET_SOURCE,
                    $locked->campaign_id,
                    'Affiliate marketing fees for campaign '.substr((string) $locked->campaign_id, 0, 8)
                );

                $locked->update([
                    'status' => MarketerCampaignFeeSettlement::STATUS_PAID,
                    'paid_via' => 'wallet',
                    'paid_at' => now(),
                ]);

                return true;
            });
        } catch (InsufficientBalanceException) {
            return false; // stays pending: the weekly statement deducts it
        } catch (\RuntimeException $e) {
            Log::warning('Affiliate marketing fee wallet debit failed; left for the statement.', ['settlement_id' => $settlement->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Hourly safety net: settle every closed campaign that still has unsettled rows, then retry the wallet for
     * pending settlements. Returns [settlements created, wallet collections].
     *
     * @return array{0: int, 1: int}
     */
    public function runSafetyNet(): array
    {
        $created = 0;
        $collected = 0;

        $campaignIds = MarketerCampaignFeeAccrual::whereNull('settlement_id')
            ->whereIn('status', [MarketerCampaignFeeAccrual::STATUS_ACCRUED, MarketerCampaignFeeAccrual::STATUS_REVERSED])
            ->distinct()->pluck('campaign_id');

        foreach (MarketerCampaign::whereIn('id', $campaignIds)->get() as $campaign) {
            if ($this->isClosed($campaign) && $this->settleIfClosed($campaign, 'scheduled')) {
                $created++;
            }
        }

        MarketerCampaignFeeSettlement::where('status', MarketerCampaignFeeSettlement::STATUS_PENDING)
            ->whereDoesntHave('payoutItems')
            ->get()
            ->each(function ($settlement) use (&$collected) {
                if ($this->collectFromWallet($settlement)) {
                    $collected++;
                }
            });

        return [$created, $collected];
    }
}
