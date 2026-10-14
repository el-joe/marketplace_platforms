<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\VendorListing;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;

/**
 * Read-only pricing preview for a vendor-listing marketer campaign.
 *
 * Everything here comes from admin-controlled settings (influencer fee per
 * country, category fee share / affiliate default, marketer_commission_rules
 * and the marketer's profile discount). The vendor never supplies a number:
 * the create form shows this quote and the server re-computes it on store.
 * Amounts are DECIMAL(19,4) base-currency units (floats here, rounded to 2 dp for display and charging).
 */
class MarketerCampaignQuoteService
{
    /** Active marketers that can be offered a campaign for this listing (same country). */
    public function eligibleMarketers(VendorListing $listing): Collection
    {
        return Marketer::where('global_status', 'active')
            ->where('country_id', $listing->country_id)
            ->orderBy('name')
            ->with(['marketerJobs', 'marketerProfile'])
            ->get();
    }

    public function listingCategory(VendorListing $listing): ?Category
    {
        return $listing->productVariant?->product?->category;
    }

    public function feePerInfluencer(?string $countryId): float
    {
        if (! $countryId) {
            return 0.0;
        }

        return (float) (MarketerInfluencerFeeCountrySetting::where('country_id', $countryId)->value('fee_per_influencer') ?? 0);
    }

    /**
     * VAT rate (percent) of the country: the same `countries.vat_rate` the
     * checkout, ad billing and financial reports use.
     */
    public function vatRate(?string $countryId): float
    {
        if (! $countryId) {
            return 0.0;
        }

        return (float) (Country::whereKey($countryId)->value('vat_rate') ?? 0);
    }

    /** VAT on a fee, rounded half up to 2 decimals (2.00 at 5% = 0.10, not 0). */
    public function vatOn(float|int|string $amount, float $ratePct): float
    {
        $amount = (float) $amount;

        return $amount > 0 && $ratePct > 0 ? round($amount * ($ratePct / 100), 2) : 0.0;
    }

    /**
     * Indicative per-sale commission for ONE unit sold at the listing price, resolved like
     * LastClickAttributionService does at sale time (AffiliateCommissionResolver): the marketer's own profile
     * rule, else the category-tab affiliate default (affiliates only, D11), else 0.
     *
     * @return array{amount: float, mode: ?string, rate: ?float, flat: ?float, source: string}
     */
    public function commissionFor(Marketer $marketer, VendorListing $listing, ?Category $category, bool $isInfluencer): array
    {
        $resolved = app(AffiliateCommissionResolver::class)
            ->resolve($marketer, $listing->country_id, $category?->id, (float) $listing->price, 1);

        return [
            'amount' => $resolved['amount'],
            'mode' => $resolved['mode'],
            'rate' => $resolved['rate'],
            'flat' => $resolved['flat'],
            'source' => $resolved['source'],
        ];
    }

    /**
     * @param  array<int, string>  $marketerIds
     * @return array{
     *   currency: string, fee_per_influencer: float, influencer_count: int, affiliate_count: int,
     *   total_fee: float, vat_rate: float, fee_vat_per_influencer: float, total_fee_vat: float, total_fee_with_vat: float, fee_payer: string, rows: array<int, array<string, mixed>>,
     *   influencer_sample_qty: int, affiliate_sample_qty: int, platform_sample_qty: int,
     *   sample_total: int, sample_count: int, stock_available: int, stock_reserved_by_other_campaigns: int, stock_min: int
     * }
     */
    public function quote(VendorListing $listing, array $marketerIds): array
    {
        $category = $this->listingCategory($listing);
        $feePer = $this->feePerInfluencer($listing->country_id);
        $currency = $listing->currency ?: (Country::find($listing->country_id)?->currency_code ?? '');

        $marketers = $marketerIds
            ? Marketer::whereIn('id', $marketerIds)
                ->where('global_status', 'active')
                ->where('country_id', $listing->country_id)
                ->with(['marketerJobs', 'marketerProfile'])
                ->get()
            : collect();

        $vatRate = $this->vatRate($listing->country_id);
        $feeVat = $this->vatOn($feePer, $vatRate);

        $rows = [];
        $influencers = 0;
        $affiliates = 0;
        foreach ($marketers as $m) {
            $isInfluencer = $m->marketerJobs->contains('key', 'influencer');
            $isInfluencer ? $influencers++ : $affiliates++;
            $perSale = $this->commissionFor($m, $listing, $category, $isInfluencer);
            $split = $isInfluencer
                ? app(MarketerFeeShareResolver::class)->split($feePer, $listing->country_id, $category?->id)
                : ['share_pct' => 0.0, 'marketer_payout' => 0.0, 'platform_keeps' => 0.0];
            // Influencers earn a share of the campaign fee (shown as their estimate); a per-sale rule is only
            // present when the admin set one on their profile. Affiliates earn the per-unit commission.
            $commission = $isInfluencer
                ? ['amount' => $split['marketer_payout'], 'mode' => null, 'rate' => $split['share_pct'], 'flat' => null, 'source' => 'fee_share']
                : $perSale;
            $rows[] = [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $isInfluencer ? 'influencer' : 'affiliate',
                'fee' => $isInfluencer ? $feePer : 0.0,
                'fee_vat' => $isInfluencer ? $feeVat : 0.0,
                'fee_total' => $isInfluencer ? round($feePer + $feeVat, 2) : 0.0,
                'commission' => $commission,
                'per_sale_commission' => $perSale,
                'fee_share_pct' => $split['share_pct'],
                'marketer_payout' => $split['marketer_payout'],
                'platform_keeps' => $split['platform_keeps'],
            ];
        }

        $sampleService = app(CampaignSampleService::class);
        $plan = $sampleService->plan($category, $influencers, $affiliates > 0);
        $minStock = (int) ($category?->min_stock_for_campaign ?? 10);

        return [
            'currency' => $currency,
            'fee_per_influencer' => $feePer,
            'influencer_count' => $influencers,
            'affiliate_count' => $affiliates,
            'total_fee' => round($influencers * $feePer, 2),
            'vat_rate' => $vatRate,
            'fee_vat_per_influencer' => $feeVat,
            'total_fee_vat' => round($influencers * $feeVat, 2),
            'total_fee_with_vat' => round($influencers * ($feePer + $feeVat), 2),
            'fee_payer' => setting('marketer_influencer_fee_payer', 'vendor') === 'vendor' ? 'vendor' : 'marketer',
            'rows' => $rows,
            // Effective quantities (admin minimums applied), see CampaignSampleService.
            'influencer_sample_qty' => $plan['influencer_each'],
            'affiliate_sample_qty' => $sampleService->affiliateQty($category),
            'platform_sample_qty' => $plan['admin'],
            'sample_total' => $plan['total'],
            'sample_count' => $plan['count'],
            'stock_available' => app(InventoryService::class)->availableStock($listing),
            'stock_reserved_by_other_campaigns' => $sampleService->pendingReservedQty($listing),
            'stock_min' => $minStock,
        ];
    }
}
