<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\VendorListing;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;

/**
 * Read-only pricing preview for a vendor-listing marketer campaign.
 *
 * Everything here comes from admin-controlled settings (influencer fee per
 * country, country/category commission settings, marketer_commission_rules
 * and the marketer's profile discount). The vendor never supplies a number:
 * the create form shows this quote and the server re-computes it on store.
 * Amounts are BIGINT base-currency units (no /100).
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

    public function feePerInfluencer(?string $countryId): int
    {
        if (! $countryId) {
            return 0;
        }

        return (int) (MarketerInfluencerFeeCountrySetting::where('country_id', $countryId)->value('fee_per_influencer') ?? 0);
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

    /** VAT on a fee (BIGINT base units, rounded half up like CheckoutPricingEngine). */
    public function vatOn(int $amount, float $ratePct): int
    {
        return $amount > 0 && $ratePct > 0 ? (int) round($amount * ($ratePct / 100)) : 0;
    }

    /**
     * Indicative commission for ONE unit sold at the listing price, resolved
     * like LastClickAttributionService does at sale time: country/category
     * setting by marketer type, else the commission rule, then the marketer's
     * profile discount.
     *
     * @return array{amount: int, mode: ?string, rate: ?float, flat: ?int, source: string}
     */
    public function commissionFor(Marketer $marketer, VendorListing $listing, ?Category $category, bool $isInfluencer): array
    {
        $price = (int) $listing->price;
        $source = 'none';
        $mode = null;
        $rate = null;
        $flat = null;
        $amount = 0;

        if ($category) {
            $setting = MarketerCommissionCountrySetting::where('country_id', $listing->country_id)
                ->where('category_id', $category->id)
                ->first();
            $perUnit = $setting
                ? (int) ($isInfluencer ? $setting->influencer_commission_amount : $setting->affiliate_commission_amount)
                : 0;
            if ($perUnit > 0) {
                $amount = $perUnit;
                $source = 'country_category';
                $mode = 'fixed';
                $flat = $perUnit;
            }
        }

        if ($amount <= 0) {
            $rule = app(CommissionRuleResolver::class)->resolve($marketer->id, 'products', $category, true);
            if ($rule) {
                $amount = $rule->resolveAmount($price, 1);
                $source = 'rule';
                $mode = $rule->commission_mode;
                $rate = (float) $rule->commission_rate;
                $flat = $rule->commission_flat_amount;
            }
        }

        return ['amount' => $amount, 'mode' => $mode, 'rate' => $rate, 'flat' => $flat, 'source' => $source];
    }

    /**
     * @param  array<int, string>  $marketerIds
     * @return array{
     *   currency: string, fee_per_influencer: int, influencer_count: int, affiliate_count: int,
     *   total_fee: int, vat_rate: float, fee_vat_per_influencer: int, total_fee_vat: int, total_fee_with_vat: int, fee_payer: string, rows: array<int, array<string, mixed>>,
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
            $commission = $this->commissionFor($m, $listing, $category, $isInfluencer);
            $rows[] = [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $isInfluencer ? 'influencer' : 'affiliate',
                'fee' => $isInfluencer ? $feePer : 0,
                'fee_vat' => $isInfluencer ? $feeVat : 0,
                'fee_total' => $isInfluencer ? $feePer + $feeVat : 0,
                'commission' => $commission,
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
            'total_fee' => $influencers * $feePer,
            'vat_rate' => $vatRate,
            'fee_vat_per_influencer' => $feeVat,
            'total_fee_vat' => $influencers * $feeVat,
            'total_fee_with_vat' => $influencers * ($feePer + $feeVat),
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
