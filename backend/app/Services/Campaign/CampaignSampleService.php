<?php

namespace App\Services\Campaign;

use App\Models\AdminListing;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Customer\CheckoutCalculationService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\InventoryService;
use App\Services\MarketerCampaignService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Promotion samples of a marketer campaign (enhancement P8 / requirement R5).
 *
 *  - ONE fixed admin (platform) sample per product campaign, created with the
 *    campaign. qty = category `platform_sample_qty`, at least
 *    `marketer_campaign_min_platform_sample_qty` (default 1).
 *  - ONE sample per influencer, created when the invitation is dispatched (not
 *    only on acceptance). qty = category `influencer_sample_qty`, at least
 *    `marketer_campaign_min_influencer_sample_qty` (default 1).
 *  - Affiliates get no per-affiliate sample: when the campaign targets
 *    affiliates a single platform-owned row of `affiliate_sample_qty` exists.
 *
 * Accounting: a sample leaves stock like a sale (once) and the admin
 * fixed + variable sale commission for listing price x qty is snapshotted on the
 * row and charged on the seller's next statement as a 0-priced line (see
 * PayoutCalculationService::selectSampleCommissions). Money is BIGINT base units.
 */
class CampaignSampleService
{
    public function minAdminQty(): int
    {
        return max(1, (int) setting('marketer_campaign_min_platform_sample_qty', 1));
    }

    public function minInfluencerQty(): int
    {
        return max(1, (int) setting('marketer_campaign_min_influencer_sample_qty', 1));
    }

    public function adminQty(?Category $category): int
    {
        return max($this->minAdminQty(), (int) ($category?->platform_sample_qty ?? 0));
    }

    public function influencerQty(?Category $category): int
    {
        return max($this->minInfluencerQty(), (int) ($category?->influencer_sample_qty ?? 0));
    }

    /** Admin-defined affiliate quantity; 0 means the platform asks for none. */
    public function affiliateQty(?Category $category): int
    {
        return max(0, (int) ($category?->affiliate_sample_qty ?? 0));
    }

    /**
     * Expected sample quantities of a campaign.
     *
     * @return array{admin: int, influencer_each: int, influencer_count: int, influencer_total: int, affiliate: int, count: int, total: int}
     */
    public function plan(?Category $category, int $influencers, bool $hasAffiliates): array
    {
        $admin = $this->adminQty($category);
        $each = $this->influencerQty($category);
        $affiliate = $hasAffiliates ? $this->affiliateQty($category) : 0;

        return [
            'admin' => $admin,
            'influencer_each' => $each,
            'influencer_count' => $influencers,
            'influencer_total' => $influencers * $each,
            'affiliate' => $affiliate,
            'count' => 1 + $influencers + ($affiliate > 0 ? 1 : 0),
            'total' => $admin + $influencers * $each + $affiliate,
        ];
    }

    /**
     * Units already promised to samples of the listing's other open campaigns
     * that have not left stock yet.
     */
    public function pendingReservedQty(VendorListing|AdminListing $listing, ?string $exceptCampaignId = null): int
    {
        $column = $listing instanceof VendorListing ? 'vendor_listing_id' : 'admin_listing_id';

        return (int) MarketerCampaignSample::query()
            ->undispatched()
            ->whereHas('campaign', fn ($q) => $q
                ->where($column, $listing->id)
                ->whereNotIn('status', MarketerCampaignService::TERMINAL_STATUSES)
                ->when($exceptCampaignId, fn ($q) => $q->where('id', '!=', $exceptCampaignId)))
            ->sum('quantity');
    }

    /** Stock that stays sellable once the planned samples (and other campaigns' pending ones) are taken. */
    public function stockAfterSamples(VendorListing|AdminListing $listing, int $plannedTotal, ?string $exceptCampaignId = null): int
    {
        return app(InventoryService::class)->availableStock($listing)
            - $this->pendingReservedQty($listing, $exceptCampaignId)
            - $plannedTotal;
    }

    public function categoryFor(MarketerCampaign $campaign): ?Category
    {
        $listing = $campaign->vendorListing ?? $campaign->adminListing;

        return $listing?->productVariant?->product?->category;
    }

    // ── Creation ────────────────────────────────────────────────────────────

    public function ensureAdminSample(MarketerCampaign $campaign, ?Category $category): ?MarketerCampaignSample
    {
        if (! ($campaign->vendor_listing_id || $campaign->admin_listing_id)) {
            return null; // travel / classified sources have no physical stock
        }

        $existing = $campaign->samples()->where('sample_type', MarketerCampaignSample::TYPE_ADMIN)->first();
        if ($existing) {
            return $existing;
        }

        return MarketerCampaignSample::create([
            'campaign_id' => $campaign->id,
            'invitation_id' => null,
            'sample_owner' => 'platform',
            'sample_type' => MarketerCampaignSample::TYPE_ADMIN,
            'quantity' => $this->adminQty($category),
            'status' => 'pending',
        ]);
    }

    /**
     * The influencer's sample, created together with the invitation. A sample
     * freed by a rejected / timed-out predecessor is re-pointed instead of
     * duplicated.
     */
    public function ensureInfluencerSample(MarketerCampaignInvitation $invitation, ?Category $category, ?MarketerCampaignSample $inherit = null): ?MarketerCampaignSample
    {
        $campaign = $invitation->campaign;
        if (! ($campaign->vendor_listing_id || $campaign->admin_listing_id)) {
            return null;
        }

        $existing = MarketerCampaignSample::where('invitation_id', $invitation->id)->live()->first();
        if ($existing) {
            return $existing;
        }

        if ($inherit && $inherit->status === 'pending' && ! $inherit->stock_deducted_at) {
            $inherit->update([
                'invitation_id' => $invitation->id,
                'status' => 'pending',
                'cancelled_at' => null,
                'quantity' => $this->influencerQty($category),
                'delivery_address_snapshot' => null,
            ]);

            return $inherit->refresh();
        }

        return MarketerCampaignSample::create([
            'campaign_id' => $campaign->id,
            'invitation_id' => $invitation->id,
            'sample_owner' => 'marketer',
            'sample_type' => MarketerCampaignSample::TYPE_INFLUENCER,
            'quantity' => $this->influencerQty($category),
            'status' => 'pending',
        ]);
    }

    /** Single platform-owned row for the whole affiliate audience (none when the admin quantity is 0). */
    public function ensureAffiliateSample(MarketerCampaign $campaign, ?Category $category): ?MarketerCampaignSample
    {
        $qty = $this->affiliateQty($category);
        if ($qty <= 0 || ! ($campaign->vendor_listing_id || $campaign->admin_listing_id)) {
            return null;
        }

        $existing = $campaign->samples()->where('sample_type', MarketerCampaignSample::TYPE_AFFILIATE)->live()->first();
        if ($existing) {
            return $existing;
        }

        return MarketerCampaignSample::create([
            'campaign_id' => $campaign->id,
            'invitation_id' => null,
            'sample_owner' => 'platform',
            'sample_type' => MarketerCampaignSample::TYPE_AFFILIATE,
            'quantity' => $qty,
            'status' => 'pending',
        ]);
    }

    // ── Cancellation / replacement ──────────────────────────────────────────

    /** The invitation's sample that has not left stock yet, if any. */
    public function undispatchedFor(MarketerCampaignInvitation $invitation): ?MarketerCampaignSample
    {
        return MarketerCampaignSample::where('invitation_id', $invitation->id)->undispatched()->first();
    }

    public function cancel(MarketerCampaignSample $sample): void
    {
        if ($sample->status !== 'pending' || $sample->stock_deducted_at) {
            return; // already out of stock: keep the audit trail untouched
        }

        $sample->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    /** Cancel every sample of the campaign that has not been dispatched yet. */
    public function cancelUndispatchedForCampaign(MarketerCampaign $campaign): int
    {
        $count = 0;
        $campaign->samples()->undispatched()->get()->each(function ($sample) use (&$count) {
            $this->cancel($sample);
            $count++;
        });

        return $count;
    }

    // ── Dispatch + accounting ───────────────────────────────────────────────

    /**
     * Send the sample out: stock is deducted and the admin sale commission is
     * snapshotted, both exactly once (row-locked, so a repeated call or a
     * concurrent click is a no-op).
     *
     * @return bool true when this call performed the stock-out
     *
     * @throws InsufficientStockException
     */
    public function dispatch(MarketerCampaignSample $sample): bool
    {
        return DB::transaction(function () use ($sample) {
            $locked = MarketerCampaignSample::whereKey($sample->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'cancelled') {
                throw new \RuntimeException('A cancelled sample cannot be dispatched.');
            }

            $data = ['status' => 'dispatched', 'dispatched_at' => $locked->dispatched_at ?? now()];
            $deducted = false;

            if (! $locked->stock_deducted_at) {
                $campaign = $locked->campaign;
                $listing = $campaign->vendorListing ?? $campaign->adminListing;

                if ($listing) {
                    app(InventoryService::class)->deductCampaignSample($listing, (int) $locked->quantity, $locked->id);
                    $data['stock_deducted_at'] = now();
                    $data['commission_amount'] = $this->commissionFor($listing, $campaign, (int) $locked->quantity);
                    $data['commission_currency'] = $campaign->currency ?? $listing->currency;
                    $deducted = true;
                }
            }

            $locked->update($data);
            $sample->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $deducted;
        });
    }

    /**
     * Admin fixed + variable sale commission for `price x qty` of the listing,
     * exactly as a normal checkout line would be charged. Platform-owned
     * (admin) listings do not pay commission to themselves.
     */
    public function commissionFor(VendorListing|AdminListing $listing, MarketerCampaign $campaign, int $qty): int
    {
        if (! $listing instanceof VendorListing) {
            return 0;
        }

        $country = $campaign->country_id ? Country::find($campaign->country_id) : null;
        $result = app(CheckoutCalculationService::class)
            ->calculateCommission($listing, $qty, (int) $listing->price, $country);

        return max(0, (int) $result['commission_amount']);
    }

    /**
     * Dispatched samples of the vendor not yet charged on any statement.
     *
     * @return Collection<int, MarketerCampaignSample>
     */
    public function unsettledFor(Vendor $vendor, string $currency): Collection
    {
        return MarketerCampaignSample::query()
            ->whereNotNull('stock_deducted_at')
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('commission_currency', $currency)->orWhereNull('commission_currency'))
            ->whereDoesntHave('payoutItems')
            ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendor->id)->where('currency', $currency))
            ->with('campaign.vendorListing.productVariant.product')
            ->orderBy('stock_deducted_at')
            ->get();
    }
}
