<?php

namespace App\Services\Marketer;

use App\Enums\MarketerType;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Notifications\Marketer\NewConversionNotification;
use App\Services\MarketerCampaignService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single entry point for recording a conversion of a marketer campaign, whatever the vertical.
 *
 * Callers resolve WHO gets the credit (LastClickAttributionService for products; the classified / travel flows for
 * theirs) and pass the invitation, the source of the sale and the commission basis. This class does the rest, under a
 * row lock on the campaign:
 *
 *   - one conversion per source, ever (idempotent);
 *   - influencers never get per-sale conversions on normal campaigns: their fixed amount is paid once at campaign
 *     completion (peer promotion is the exception: the promoter earns his profile rule per sale);
 *   - gross / fee / net are computed by MarketerCommissionCalculator and snapshotted on the row;
 *   - the Max Commission Budget is never exceeded: the last commission is shortened to what is left, and the
 *     campaign is marked done as soon as nothing useful is left (commission_budget_spent + influencer_fixed_reserved);
 *   - reverse() undoes a conversion (cancelled / refunded sale) and frees its budget.
 *
 * Basis per vertical (campaign currency): product = item price excluding VAT after discounts for the whole line;
 * classified = the sold price; travel = the booking total.
 */
class CampaignSaleRecorder
{
    public function __construct(
        private readonly MarketerCommissionCalculator $calculator,
        private readonly MarketerCommissionPostingService $postings,
    ) {}

    /**
     * @param  int  $quantity  sold items (multiplies a broker's fixed part)
     * @param  string|null  $categoryId  category the rules are looked up by (defaults to the campaign's)
     * @return MarketerCampaignConversion|null null when nothing was recorded (influencer, closed campaign, exhausted budget)
     */
    public function record(
        MarketerCampaignInvitation $invitation,
        ConversionSource $source,
        float $basis,
        int $quantity = 1,
        ?string $categoryId = null,
        ?CarbonInterface $triggeredAt = null,
        ?CarbonInterface $clickedAt = null,
    ): ?MarketerCampaignConversion {
        $budgetDone = false;
        $conversion = null;

        DB::transaction(function () use ($invitation, $source, $basis, $quantity, $categoryId, $triggeredAt, $clickedAt, &$conversion, &$budgetDone) {
            /** @var MarketerCampaign $campaign */
            $campaign = MarketerCampaign::whereKey($invitation->campaign_id)->lockForUpdate()->firstOrFail();
            $invitation = MarketerCampaignInvitation::with('marketer')->findOrFail($invitation->id);

            if ($source->existing() || ! in_array($campaign->status, ['active', 'auto_approved'], true)) {
                return;
            }

            $marketer = $invitation->marketer;
            if (! $marketer) {
                return;
            }

            // A peer promoter (an influencer promoting another influencer's listing) is paid per sale by his profile rule.
            $peer = $campaign->isPeerPromotion();
            $result = $this->calculator->compute($marketer, $campaign, $basis, $quantity, $invitation, $categoryId, null, $peer);
            if ($result->marketerType === MarketerType::Influencer && ! $peer) {
                return; // paid once per campaign by InfluencerFixedService, never per sale
            }

            $remaining = $campaign->budgetRemaining();
            if ($remaining !== null) {
                if ($remaining < 0.01) {
                    $budgetDone = true;

                    return;
                }
                if ($result->gross > $remaining) {
                    $result = $result->withGross($remaining);
                }
            }

            $conversion = MarketerCampaignConversion::create($source->columns + [
                'campaign_id' => $campaign->id,
                'invitation_id' => $invitation->id,
                'conversion_type' => $source->type,
                'referral_clicked_at' => $clickedAt,
                'triggered_at' => $triggeredAt ?? now(),
                'currency' => $campaign->currency,
                'status' => 'pending',
                'commissioned' => false,
                'sale_amount' => $result->basis,
                'commission_amount' => $result->gross,
                'gross_commission_amount' => $result->gross,
                'commission_fee_amount' => $result->fee,
                'net_commission_amount' => $result->net,
                'marketer_type' => $result->marketerType->value,
                'rule_source' => $result->ruleSource,
            ]);

            $invitation->increment('total_conversions');
            $invitation->increment('total_commission_earned', $result->gross);
            $campaign->increment('commission_budget_spent', $result->gross);
            $campaign->refresh();

            $budgetDone = $campaign->isBudgetExhausted();

            $marketer->marketerAdmins->each(
                fn ($ma) => $ma->notify(new NewConversionNotification($conversion, $ma->id))
            );
        });

        if ($budgetDone) {
            $this->finishCampaign($invitation->campaign_id);
        }

        return $conversion;
    }

    /**
     * Undo a conversion (order item cancelled / returned, booking cancelled): wallet and ledger postings are reversed
     * and the commission goes back into the campaign budget. A campaign that already finished is NOT reopened.
     * A conversion already paid out in a marketer payout is left alone (manual recovery).
     */
    public function reverse(MarketerCampaignConversion $conversion): bool
    {
        return DB::transaction(function () use ($conversion) {
            $locked = MarketerCampaignConversion::whereKey($conversion->id)->lockForUpdate()->first();
            if (! $locked || ! in_array($locked->status, ['pending', 'approved'], true)) {
                return false;
            }

            $marketerId = $locked->invitation?->marketer_id;
            $this->postings->reverse($locked, $marketerId);

            $freed = round((float) $locked->commission_amount, 2);
            $campaign = MarketerCampaign::whereKey($locked->campaign_id)->lockForUpdate()->first();
            if ($campaign && $freed > 0) {
                $campaign->update(['commission_budget_spent' => max(0.0, round((float) $campaign->commission_budget_spent - $freed, 2))]);
            }
            $locked->invitation?->update([
                'total_conversions' => max(0, (int) $locked->invitation->total_conversions - 1),
                'total_commission_earned' => max(0.0, round((float) $locked->invitation->total_commission_earned - $freed, 2)),
            ]);

            $locked->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'commissioned' => false,
                'paid_at' => null,
            ]);

            return true;
        });
    }

    private function finishCampaign(string $campaignId): void
    {
        $campaign = MarketerCampaign::find($campaignId);
        if (! $campaign || ! in_array($campaign->status, ['active', 'auto_approved', 'paused'], true)) {
            return;
        }

        Log::info('MarketerCampaign done: commission budget exhausted.', [
            'campaign_id' => $campaign->id,
            'spent' => $campaign->commission_budget_spent,
            'reserved' => $campaign->influencer_fixed_reserved,
            'budget' => $campaign->max_commission_budget,
        ]);

        app(MarketerCampaignService::class)->markCampaignDone($campaign);
    }
}
