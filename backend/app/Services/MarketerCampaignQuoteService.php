<?php

namespace App\Services;

use App\Enums\MarketerType;
use App\Models\Category;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\TravelPackage;
use App\Models\VendorListing;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InventoryService;
use App\Services\Marketer\InfluencerFixedService;
use App\Services\Marketer\MarketerCommissionCalculator;
use App\Support\Marketer\CampaignBudgetRules;
use App\Support\Marketer\CampaignSubject;
use Illuminate\Support\Collection;

/**
 * Read-only pricing preview for a marketer campaign of any vertical (the wizard's cost summary and the server-side
 * snapshot taken on store). Every method takes the promoted item: a VendorListing (product), a ClassifiedListing or a
 * TravelPackage (trip), or the CampaignSubject wrapping one. The owner types only the Max Commission Budget;
 * everything else comes from admin settings:
 *
 *   campaign fee (fixed + variable)      MarketingFeeService on the base (the budget; for an UNLIMITED campaign the
 *                                        influencers' fixed total); the variable part is per category and fulfilment
 *                                        type for products, per country and vertical for classified and travel
 *   influencers' fixed total             sum of each picked influencer's fixed amount for this product category,
 *                                        reserved from the budget when the campaign is created
 *   remaining for affiliates / brokers   budget - influencers' fixed total (null = unlimited); their percentage
 *                                        commissions are paid from it per sold item until it is used up, then the campaign is done
 *   grand total                          base + campaign fee
 *
 * The budget is optional: blank / 0 is UNLIMITED (an end date is then required, flag requires_end_date) and a given
 * budget must be strictly greater than the influencers' fixed total (fits_budget, shortfall).
 *
 * Amounts are DECIMAL(19,4) base-currency units (floats here, rounded to 2 dp for display and charging).
 */
class MarketerCampaignQuoteService
{
    public function __construct(
        private readonly MarketerCommissionCalculator $calculator,
        private readonly InfluencerFixedService $influencerFixed,
        private readonly MarketingFeeService $marketingFees,
    ) {}

    /** Normalise whatever the caller holds into the vertical-neutral subject. */
    public function subject(CampaignSubject|VendorListing|ClassifiedListing|TravelPackage $item): CampaignSubject
    {
        return $item instanceof CampaignSubject ? $item : CampaignSubject::of($item);
    }

    /** Active marketers that can be offered a campaign for this item (same country). */
    public function eligibleMarketers(CampaignSubject|VendorListing|ClassifiedListing|TravelPackage $item): Collection
    {
        $subject = $this->subject($item);

        return Marketer::where('global_status', 'active')
            ->where('country_id', $subject->countryId)
            ->orderBy('name')
            ->with(['marketerJobs', 'marketerProfile'])
            ->get();
    }

    /** Active influencers of the item's country, each with the fixed amount he would be paid for this item. */
    public function influencerOptions(CampaignSubject|VendorListing|ClassifiedListing|TravelPackage $item): Collection
    {
        $subject = $this->subject($item);
        $context = $subject->context();

        return $this->eligibleMarketers($subject)
            ->filter(fn (Marketer $m) => in_array(MarketerType::Influencer, MarketerType::heldBy($m), true))
            ->values()
            ->map(function (Marketer $m) use ($subject, $context) {
                $fixed = $this->calculator->influencerFixedFor(
                    $m, $subject->countryId, MarketerCommissionCalculator::verticalOf($context), $this->calculator->categoryIdFor($context)
                );

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'amount' => $fixed['amount'],
                    'source' => $fixed['source'],
                    'story_price' => $m->marketerProfile?->story_price,
                    'post_price' => $m->marketerProfile?->post_price,
                    'video_price' => $m->marketerProfile?->video_price,
                ];
            });
    }

    /** Number of active affiliates / brokers a campaign "open to all" would reach. */
    public function openAudienceCount(CampaignSubject|VendorListing|ClassifiedListing|TravelPackage $item): int
    {
        return $this->eligibleMarketers($item)
            ->filter(fn (Marketer $m) => array_filter(MarketerType::heldBy($m), fn (MarketerType $t) => $t !== MarketerType::Influencer) !== [])
            ->count();
    }

    public function listingCategory(VendorListing $listing): ?Category
    {
        return $listing->productVariant?->product?->category;
    }

    /**
     * Adds the owner's wallet comparison against the campaign fee, computed server-side so the form does no money math.
     *
     * @param  array<string, mixed>  $quote
     * @param  string  $ownerType  wallet owner type: vendor | travel_agency
     * @return array<string, mixed>
     */
    public function withWallet(array $quote, string $ownerId, ?string $currency, string $ownerType = 'vendor'): array
    {
        $wallet = app(WalletService::class)->getOrCreateWallet($ownerType, $ownerId, $currency ?: ($quote['currency'] ?? ''));
        $balance = (float) $wallet->balance;
        // Only the campaign fee is debited from the wallet; commissions are deducted from the vendor's payouts (or
        // the agency's bookings) as they are earned. The wallet comparison therefore uses the fee.
        $fee = (float) ($quote['marketing']['total'] ?? 0);

        $quote['wallet'] = [
            'balance' => round($balance, 2),
            'currency' => $wallet->currency,
            'shortfall' => round(max(0.0, $fee - $balance), 2),
            'sufficient' => $balance >= $fee,
        ];

        return $quote;
    }

    /**
     * The campaign fee on a Max Commission Budget: product campaigns by category and fulfilment type, classified and
     * travel by country and vertical.
     *
     * @return array{fixed_fee: float, variable_fee: float, total_fee: float}
     */
    public function feeFor(CampaignSubject|VendorListing|ClassifiedListing|TravelPackage $item, float $budget): array
    {
        $subject = $this->subject($item);

        return $subject->isProduct()
            ? $this->marketingFees->calculate($budget, $subject->countryId, $subject->categoryId, $subject->feeType())
            : $this->marketingFees->calculateForVertical($budget, $subject->countryId, $subject->vertical);
    }

    /**
     * @param  array<int, string>  $marketerIds  picked influencer ids (other ids are ignored here)
     * @return array{
     *   vertical: string, currency: string, budget: float, influencer_count: int, affiliate_count: int,
     *   influencer_fixed_total: float, remaining_budget: ?float, fits_budget: bool, shortfall: float, unlimited: bool,
     *   requires_end_date: bool, open_to_affiliates: bool,
     *   rows: array<int, array{id: string, name: string, type: string, fixed: float, source: string}>,
     *   fulfillment_type: string, fulfillment_type_label: string,
     *   marketing: array{fixed_fee: float, variable_fee: float, total: float},
     *   campaign_subtotal: float, campaign_grand_total: float,
     *   influencer_sample_qty: int, affiliate_sample_qty: int, platform_sample_qty: int,
     *   sample_total: int, sample_count: int, stock_available: int, stock_reserved_by_other_campaigns: int, stock_min: int
     * }
     */
    public function quote(CampaignSubject|VendorListing|ClassifiedListing|TravelPackage $item, array $marketerIds, float $budget = 0.0, bool $openToAffiliates = false): array
    {
        $subject = $this->subject($item);
        $currency = $subject->currency ?: (Country::find($subject->countryId)?->currency_code ?? '');
        $budget = max(0.0, round($budget, 2));

        $influencerIds = array_values(array_unique($marketerIds));
        $influencers = $influencerIds
            ? Marketer::whereIn('id', $influencerIds)
                ->where('global_status', 'active')
                ->where('country_id', $subject->countryId)
                ->with(['marketerJobs', 'marketerProfile'])
                ->get()
                ->filter(fn (Marketer $m) => in_array(MarketerType::Influencer, MarketerType::heldBy($m), true))
            : collect();

        $snapshot = $this->influencerFixed->snapshot($influencers, $subject->countryId, $subject->context());
        $fixedTotal = $this->influencerFixed->total($snapshot);

        $rows = $influencers->map(fn (Marketer $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'type' => MarketerType::Influencer->value,
            'fixed' => $snapshot[$m->id]['amount'],
            'source' => $snapshot[$m->id]['source'],
        ])->values()->all();

        $affiliates = $openToAffiliates ? $this->openAudienceCount($subject) : 0;

        // Unlimited (no budget): the campaign is priced on what is certain, the influencers' fixed total.
        $unlimited = CampaignBudgetRules::isUnlimited($budget);
        $base = $unlimited ? $fixedTotal : $budget;
        $fits = $unlimited || ! CampaignBudgetRules::budgetTooSmall($budget, $fixedTotal);

        $marketing = $this->feeFor($subject, $base);

        // Samples and stock only exist for products; the other verticals carry no physical stock.
        $samples = ['influencer_each' => 0, 'admin' => 0, 'total' => 0, 'count' => 0];
        $affiliateSampleQty = 0;
        $reserved = 0;
        $stock = 0;
        $minStock = 0;
        if ($subject->isProduct()) {
            $category = $subject->productCategory();
            $sampleService = app(CampaignSampleService::class);
            $samples = $sampleService->plan($category, $influencers->count(), $affiliates > 0);
            $affiliateSampleQty = $sampleService->affiliateQty($category);
            $reserved = $sampleService->pendingReservedQty($subject->model);
            $stock = app(InventoryService::class)->availableStock($subject->model);
            $minStock = (int) ($category?->min_stock_for_campaign ?? 10);
        }

        $feeType = $subject->feeType();

        return [
            'vertical' => $subject->vertical->value,
            'currency' => $currency,
            'budget' => $budget,
            'influencer_count' => $influencers->count(),
            'affiliate_count' => $affiliates,
            'influencer_fixed_total' => $fixedTotal,
            'remaining_budget' => $unlimited ? null : round($budget - $fixedTotal, 2),
            'fits_budget' => $fits,
            'shortfall' => $fits ? 0.0 : CampaignBudgetRules::shortfall($budget, $fixedTotal),
            'unlimited' => $unlimited,
            'requires_end_date' => $unlimited,
            'open_to_affiliates' => $openToAffiliates,
            'rows' => $rows,
            'fulfillment_type' => $feeType?->value ?? $subject->vertical->value,
            'fulfillment_type_label' => $feeType?->label() ?? $subject->vertical->label(),
            'marketing' => [
                'fixed_fee' => $marketing['fixed_fee'],
                'variable_fee' => $marketing['variable_fee'],
                'total' => $marketing['total_fee'],
            ],
            'campaign_subtotal' => round($base, 2),
            'campaign_grand_total' => round($base + $marketing['total_fee'], 2),
            // Effective quantities (admin minimums applied), see CampaignSampleService.
            'influencer_sample_qty' => $samples['influencer_each'],
            'affiliate_sample_qty' => $affiliateSampleQty,
            'platform_sample_qty' => $samples['admin'],
            'sample_total' => $samples['total'],
            'sample_count' => $samples['count'],
            'stock_available' => $stock,
            'stock_reserved_by_other_campaigns' => $reserved,
            'stock_min' => $minStock,
        ];
    }
}
