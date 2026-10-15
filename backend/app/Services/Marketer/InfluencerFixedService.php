<?php

namespace App\Services\Marketer;

use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The influencer fixed amount of a campaign (one amount per influencer per campaign, never per sale).
 *
 * Lifecycle, all inside the campaign's Max Commission Budget:
 *   1. snapshot()    the wizard/creation freezes each picked influencer's amount (profile rule, else platform default);
 *   2. reserve()     the snapshot total is held back from the budget when the campaign is created (rejected if it does
 *                    not fit); marketer_campaigns.influencer_fixed_reserved tracks it;
 *   3. applyTo()     when the invitation is dispatched the amount is copied onto it (status reserved);
 *   4. release()     a rejected / timed-out / cancelled invitation (or a campaign that ends before it was accepted)
 *                    returns its amount to the budget;
 *   5. payAll()      at campaign completion every still-accepted invitation is paid: wallet credit net of the
 *                    commission fee plus the platform fee ledger entry.
 *
 * Budget used = commission_budget_spent (affiliate / broker commissions) + influencer_fixed_reserved.
 */
class InfluencerFixedService
{
    public function __construct(
        private readonly MarketerCommissionCalculator $calculator,
        private readonly MarketerCommissionPostingService $postings,
    ) {}

    /**
     * Freeze the fixed amount of each influencer for a campaign context.
     *
     * @param  iterable<Marketer>  $marketers
     * @return array<string, array{amount: float, source: string}> keyed by marketer id
     */
    public function snapshot(iterable $marketers, ?string $countryId, MarketerCampaign $context): array
    {
        $vertical = MarketerCommissionCalculator::verticalOf($context);
        $categoryId = $this->calculator->categoryIdFor($context);

        $snapshot = [];
        foreach ($marketers as $marketer) {
            $snapshot[$marketer->id] = $this->calculator->influencerFixedFor($marketer, $countryId, $vertical, $categoryId);
        }

        return $snapshot;
    }

    public function total(array $snapshot): float
    {
        return round(array_sum(array_map(fn (array $row) => (float) $row['amount'], $snapshot)), 2);
    }

    /** Budget not yet claimed by affiliate / broker commissions or influencer fixed amounts. */
    public function remainingBudget(MarketerCampaign $campaign): float
    {
        return max(0.0, round(
            (float) $campaign->max_commission_budget - (float) $campaign->commission_budget_spent - (float) $campaign->influencer_fixed_reserved,
            2
        ));
    }

    /**
     * Store the snapshot on the campaign and hold its total back from the budget.
     *
     * @param  array<string, array{amount: float, source: string}>  $snapshot
     *
     * @throws \RuntimeException when the influencers' fixed amounts exceed the Max Commission Budget
     */
    public function reserve(MarketerCampaign $campaign, array $snapshot): void
    {
        $total = $this->total($snapshot);
        $budget = round((float) $campaign->max_commission_budget, 2);
        if ($budget > 0 && $total > $budget + 0.004) {
            throw new \RuntimeException(__('marketer_campaigns.errors.fixed_exceeds_budget', [
                'fixed' => Money::format($total), 'budget' => Money::format($campaign->max_commission_budget),
            ]));
        }

        $campaign->update([
            'influencer_fixed_snapshot' => $snapshot ?: null,
            'influencer_fixed_reserved' => $total,
        ]);
    }

    /** Copy the campaign snapshot entry of this marketer onto his freshly dispatched invitation. */
    public function applyTo(MarketerCampaign $campaign, MarketerCampaignInvitation $invitation): void
    {
        $entry = ((array) $campaign->influencer_fixed_snapshot)[$invitation->marketer_id] ?? null;
        if (! $entry) {
            return;
        }

        $invitation->update([
            'influencer_fixed_amount' => (float) $entry['amount'],
            'influencer_fixed_source' => $entry['source'] ?? MarketerCommissionCalculator::SOURCE_NONE,
            'influencer_fixed_status' => MarketerCampaignInvitation::FIXED_RESERVED,
            'influencer_fixed_reserved_at' => now(),
        ]);
    }

    /**
     * Reserve an influencer added to (or replacing another in) a running campaign, with his own amount, if it still
     * fits the budget. The predecessor's reservation, when given, goes back to the budget first.
     *
     * @return bool false when it does not fit (the predecessor's release is rolled back too)
     */
    public function reserveFor(MarketerCampaign $campaign, Marketer $influencer, ?MarketerCampaignInvitation $replacing = null): bool
    {
        return DB::transaction(function () use ($campaign, $influencer, $replacing) {
            $locked = MarketerCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            $freed = $replacing && $replacing->influencer_fixed_status === MarketerCampaignInvitation::FIXED_RESERVED
                ? (float) $replacing->influencer_fixed_amount
                : 0.0;

            $entry = $this->snapshot([$influencer], $locked->country_id, $locked)[$influencer->id];
            if ((float) $locked->max_commission_budget > 0 && $entry['amount'] > $this->remainingBudget($locked) + $freed + 0.004) {
                return false;
            }

            if ($replacing) {
                $this->release($replacing, $locked);
                $locked->refresh();
            }

            $snapshot = (array) $locked->influencer_fixed_snapshot;
            $snapshot[$influencer->id] = $entry;
            $locked->update([
                'influencer_fixed_snapshot' => $snapshot,
                'influencer_fixed_reserved' => round((float) $locked->influencer_fixed_reserved + $entry['amount'], 2),
            ]);
            $campaign->refresh();

            return true;
        });
    }

    /** Return an unpaid reservation of this invitation to the budget (idempotent). */
    public function release(MarketerCampaignInvitation $invitation, ?MarketerCampaign $campaign = null): void
    {
        if ($invitation->influencer_fixed_status !== MarketerCampaignInvitation::FIXED_RESERVED) {
            return;
        }

        $amount = (float) $invitation->influencer_fixed_amount;
        $invitation->update([
            'influencer_fixed_status' => MarketerCampaignInvitation::FIXED_RELEASED,
            'influencer_fixed_released_at' => now(),
        ]);

        if ($amount > 0) {
            $campaign ??= MarketerCampaign::whereKey($invitation->campaign_id)->lockForUpdate()->first();
            $campaign?->update([
                'influencer_fixed_reserved' => max(0.0, round((float) $campaign->influencer_fixed_reserved - $amount, 2)),
            ]);
        }
    }

    /**
     * Campaign over: pay every accepted influencer, release the rest. Idempotent (influencers_settled_at).
     *
     * @return array{paid: int, released: int}
     */
    public function settleCampaign(MarketerCampaign $campaign): array
    {
        $result = ['paid' => 0, 'released' => 0];

        DB::transaction(function () use ($campaign, &$result) {
            $locked = MarketerCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->influencers_settled_at) {
                return;
            }

            $invitations = MarketerCampaignInvitation::where('campaign_id', $locked->id)
                ->where('influencer_fixed_status', MarketerCampaignInvitation::FIXED_RESERVED)
                ->with('marketer')
                ->lockForUpdate()
                ->get();

            foreach ($invitations as $invitation) {
                if ($invitation->status === 'accepted') {
                    $this->pay($locked, $invitation);
                    $result['paid']++;
                } else {
                    $this->release($invitation, $locked);
                    $locked->refresh();
                    $result['released']++;
                }
            }

            $locked->update(['influencers_settled_at' => now()]);
        });

        return $result;
    }

    /** Pay one fulfilled influencer: wallet credit net of the commission fee + platform fee ledger entry. */
    private function pay(MarketerCampaign $campaign, MarketerCampaignInvitation $invitation): void
    {
        $result = $this->calculator->compute($invitation->marketer, $campaign, 0, 1, $invitation);

        $posted = $result->gross > 0
            ? $this->postings->payFixed(
                (string) $invitation->id,
                (string) $invitation->marketer_id,
                $campaign->vendor_id,
                (string) $campaign->currency,
                $result->gross,
                $result->fee,
                $result->net,
                __('marketer.finance.tx_influencer_fixed', ['campaign' => $campaign->getPromotedTitle() ?: '#'.substr((string) $campaign->id, 0, 8)])
            )
            : ['wallet_tx_id' => '', 'fee' => 0.0, 'net' => 0.0];

        $invitation->update([
            'influencer_fixed_status' => MarketerCampaignInvitation::FIXED_PAID,
            'influencer_fixed_paid_at' => now(),
            'influencer_fixed_fee_amount' => $result->fee,
            'influencer_fixed_net_amount' => $result->net,
            'influencer_fixed_wallet_tx_id' => $posted['wallet_tx_id'] ?: null,
            'total_commission_earned' => round((float) $invitation->total_commission_earned + $result->gross, 2),
        ]);
    }
}
