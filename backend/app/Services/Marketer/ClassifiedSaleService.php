<?php

namespace App\Services\Marketer;

use App\Enums\ClassifiedListingStatus;
use App\Enums\MarketerType;
use App\Models\ClassifiedListing;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marking a classified listing sold, and taking it back.
 *
 * A classified sale happens between the seller and the buyer, off the platform, so the platform only learns about it
 * when the seller says so. While a marketer campaign is live on the listing the seller must therefore enter the final
 * sold price (the commission basis) and, optionally, which marketer's buyer it was and a note about the buyer. Then:
 *
 *   - sold_price / sold_at / sold_buyer_note / sold_via_campaign_id are saved on the listing;
 *   - the chosen affiliate / broker earns a conversion through CampaignSaleRecorder (ConversionSource::classifiedSale,
 *     basis = sold price, one per listing); influencers never get per-sale conversions;
 *   - the listing's open campaigns are completed (nothing is left to promote), which also pays fulfilled influencers.
 *
 * Un-marking the sale reverses the conversion (budget freed, postings undone) and clears the sold_* fields. A campaign
 * that was completed by the sale is not reopened: the seller creates a new one. A commission that has already been paid
 * out can no longer be reversed, so the sale then cannot be taken back.
 */
class ClassifiedSaleService
{
    public function __construct(
        private readonly CampaignSaleRecorder $recorder,
        private readonly MarketerCampaignService $campaigns,
    ) {}

    /** The campaign currently running on the listing (it is the one a sale is credited to), if any. */
    public function liveCampaign(ClassifiedListing $listing): ?MarketerCampaign
    {
        return MarketerCampaign::where('classified_listing_id', $listing->id)
            ->whereIn('status', ['active', 'auto_approved'])
            ->latest()
            ->first();
    }

    /**
     * Marketers the seller can credit with the sale: everyone with an accepted affiliate / broker invitation on the
     * campaign, with the number of buyers' inquiries each one referred.
     *
     * @return Collection<int, array{id: string, name: string, inquiries: int}>
     */
    public function creditableMarketers(MarketerCampaign $campaign): Collection
    {
        $referred = $campaign->classifiedListing
            ? $campaign->classifiedListing->inquiries()->where('marketer_campaign_id', $campaign->id)->whereNotNull('marketer_id')
                ->selectRaw('marketer_id, count(*) as total')->groupBy('marketer_id')->pluck('total', 'marketer_id')
            : collect();

        return $campaign->invitations()
            ->where('status', 'accepted')
            ->with('marketer.marketerJobs')
            ->get()
            ->filter(fn (MarketerCampaignInvitation $i) => $i->marketer
                && array_intersect([MarketerType::Affiliate, MarketerType::Broker], MarketerType::heldBy($i->marketer)) !== [])
            ->map(fn (MarketerCampaignInvitation $i) => [
                'id' => $i->marketer_id,
                'name' => $i->marketer->name,
                'inquiries' => (int) ($referred[$i->marketer_id] ?? 0),
            ])
            ->sortByDesc('inquiries')
            ->values();
    }

    public function markSold(ClassifiedListing $listing, ?float $soldPrice = null, ?string $buyerNote = null, ?string $marketerId = null): ClassifiedListing
    {
        if (! in_array($listing->status, [ClassifiedListingStatus::Active, ClassifiedListingStatus::Paused], true)) {
            throw ValidationException::withMessages(['status' => __('marketer_campaigns.classified.cannot_mark_sold')]);
        }

        $campaign = $this->liveCampaign($listing);

        if ($campaign && ($soldPrice === null || $soldPrice <= 0)) {
            throw ValidationException::withMessages(['sold_price' => __('marketer_campaigns.classified.sold_price_required')]);
        }

        $invitation = null;
        if ($campaign && $marketerId) {
            $invitation = $campaign->invitations()->where('marketer_id', $marketerId)->where('status', 'accepted')->first();
            if (! $invitation || ! $this->creditableMarketers($campaign)->contains('id', $marketerId)) {
                throw ValidationException::withMessages(['marketer_id' => __('marketer_campaigns.classified.marketer_not_in_campaign')]);
            }
        }

        $soldPrice = $soldPrice !== null && $soldPrice > 0 ? round($soldPrice, 2) : null;

        DB::transaction(function () use ($listing, $campaign, $invitation, $soldPrice, $buyerNote) {
            $listing->update([
                'status' => ClassifiedListingStatus::Sold,
                'sold_price' => $soldPrice,
                'sold_at' => now(),
                'sold_buyer_note' => $buyerNote ?: null,
                'sold_via_campaign_id' => $invitation ? $campaign->id : null,
            ]);

            if ($invitation) {
                $this->recorder->record(
                    $invitation,
                    ConversionSource::classifiedSale((string) $listing->id),
                    (float) $soldPrice,
                    1,
                    $listing->classified_category_id,
                    now(),
                );
            }
        });

        // Nothing left to promote: complete every open campaign of the listing (pays fulfilled influencers).
        MarketerCampaign::where('classified_listing_id', $listing->id)
            ->whereNotIn('status', MarketerCampaignService::TERMINAL_STATUSES)
            ->get()
            ->each(fn (MarketerCampaign $open) => $open->status === 'pending_admin'
                ? $this->campaigns->cancelCampaign($open)
                : $this->campaigns->markCampaignDone($open));

        return $listing->fresh();
    }

    public function unmarkSold(ClassifiedListing $listing): ClassifiedListing
    {
        if ($listing->status !== ClassifiedListingStatus::Sold) {
            throw ValidationException::withMessages(['status' => __('marketer_campaigns.classified.not_sold')]);
        }

        $conversion = MarketerCampaignConversion::where('classified_listing_id', $listing->id)
            ->where('conversion_type', MarketerCampaignConversion::TYPE_CLASSIFIED_SALE)
            ->first();

        DB::transaction(function () use ($listing, $conversion) {
            if ($conversion && $conversion->status !== 'reversed' && ! $this->recorder->reverse($conversion)) {
                throw ValidationException::withMessages(['status' => __('marketer_campaigns.classified.cannot_unmark_paid')]);
            }

            $listing->update([
                'status' => ClassifiedListingStatus::Active,
                'sold_price' => null,
                'sold_at' => null,
                'sold_buyer_note' => null,
                'sold_via_campaign_id' => null,
            ]);
        });

        return $listing->fresh();
    }
}
