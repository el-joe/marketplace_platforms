<?php

namespace App\Services\Customer;

use App\Enums\AdCampaignType;
use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\AdImpression;
use App\Models\Country;
use App\Models\Product;
use App\Models\VendorListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SponsoredProductService
{
    private const SPONSORED_SLOTS = [1, 5, 9];

    public function __construct(private ListingQueryService $listings)
    {
    }

    /**
     * Inject sponsored products at fixed positions 1, 5, 9 (1-based) into a paginated items array.
     * Only injects on page 1.
     *
     * @param array $items         Already-paginated product items (from resource collection resolve)
     * @param Country $country
     * @param int $page
     * @param string $placement    e.g. 'search_results', 'category_top'
     * @param string|null $query
     * @param list<string> $categoryIds       When set, restricts sponsored items to these category IDs
     * @param array<string,list<string>> $attributeFilters  Attribute code => allowed values, mirrors ProductQueryService::applyFilters
     * @param list<int> $slots  1-based positions to inject sponsored items at; defaults to SPONSORED_SLOTS
     * @return array
     */
    public function inject(
        array $items,
        Country $country,
        int $page,
        string $placement = 'search_results',
        ?string $query = null,
        array $categoryIds = [],
        array $attributeFilters = [],
        array $slots = self::SPONSORED_SLOTS,
    ): array {
        if ($page !== 1) {
            return $items;
        }

        $sponsored = $this->fetchSponsored($country, count($slots), $categoryIds, $attributeFilters);

        if ($sponsored->isEmpty()) {
            return $items;
        }

        \App\Services\Customer\PromoBadgeResolver::instance()->prime(\App\Services\Customer\PromoBadgeResolver::tuplesForListings($sponsored));

        foreach ($slots as $position) {
            if ($sponsored->isEmpty()) {
                break;
            }

            $listing = $sponsored->shift();
            $product = $listing->productVariant->product ?? null;

            if (!$product) {
                continue;
            }

            // Shape sponsored items through the exact same card builder used for
            // normal listings (and by the page builder) so the frontend always
            // gets a consistent flat shape — price, currency, images, etc.
            $sponsoredItem = array_merge(
                $this->listings->toCardShape(
                    listing: $listing,
                    product: $product,
                    country: $country,
                    isWishlisted: false,
                    isSponsored: true,
                ),
                ['_sponsored_listing_id' => $listing->id]
            );

            array_splice($items, $position - 1, 0, [$sponsoredItem]);

            $this->recordImpression($listing, $country, $placement, $position, $query);
        }

        return $items;
    }

    /**
     * Fetches one sponsored listing to show in the product-page ad bar.
     *
     * Picks the highest-scoring active campaign whose products share the same
     * category as $categoryId, excluding the currently viewed product so the
     * ad is never the same item. Returns null when no eligible campaign exists.
     *
     * @return array{listing_id: string, campaign_id: string, campaign_type: string, product_id: ?string, product_slug: ?string, url_param: string, name: array, thumbnail: ?string, price: int, currency: string, shipping_badge: ?array, is_express: bool, impression_id: string}|null
     */
    public function forProductPage(
        Country $country,
        string $categoryId,
        string $excludeProductId,
        ?string $customerId,
        ?string $sessionId,
    ): ?array {
        $listing = VendorListing::query()
            ->with(['productVariant.product.images', 'productVariant.images', 'primaryShippingMethod'])
            ->join('ad_campaign_products as acp', 'acp.vendor_listing_id', '=', 'vendor_listings.id')
            ->join('ad_campaigns as ac', function ($j) use ($country) {
                $j->on('ac.id', '=', 'acp.ad_campaign_id')
                  ->where('ac.country_id', $country->id)
                  ->where('ac.status', 'active')
                  ->where(function ($q) {
                      $q->whereNull('ac.ends_at')->orWhere('ac.ends_at', '>', now());
                  });
            })
            ->whereHas('productVariant.product', fn ($q) => $q->where('category_id', $categoryId)
                ->where('id', '!=', $excludeProductId))
            ->where('vendor_listings.status', 'active')
            ->where('vendor_listings.country_id', $country->id)
            ->where('acp.is_active', true)
            ->where(function ($q) {
                $q->whereRaw('ac.budget_spent_total < ac.budget_total')
                  ->where(function ($q2) {
                      $q2->whereNull('ac.budget_daily')->orWhereRaw('ac.budget_spent_today < ac.budget_daily');
                  });
            })
            ->orderByDesc('ac.quality_score')
            ->orderByDesc('ac.bid')
            ->select(
                'vendor_listings.*',
                'ac.id as _campaign_id',
                'ac.type as _campaign_type',
                'ac.bid as _bid',
                'ac.quality_score as _quality_score',
            )
            ->first();

        if (!$listing) {
            return null;
        }

        $impressionId = (string) Str::uuid();
        $campaignId   = $listing->_campaign_id;
        $bid          = $listing->_bid;
        $qualityScore = $listing->_quality_score;

        dispatch(function () use ($listing, $country, $customerId, $sessionId, $impressionId, $campaignId, $bid, $qualityScore) {
            $campaign = AdCampaign::find($campaignId);
            $cost     = $campaign ? $this->cpmCostForImpression($campaign) : 0;

            AdImpression::create([
                'id'                          => $impressionId,
                'ad_campaign_id'              => $campaignId,
                'vendor_listing_id'           => $listing->id,
                'customer_id'                 => $customerId,
                'session_id'                  => $sessionId ?? Str::random(26),
                'placement_code'              => 'product_page_top',
                'search_query'                => null,
                'position_shown'              => 1,
                'bid_at_impression'           => $bid ?? 0,
                'quality_score_at_impression' => $qualityScore ?? 0,
                'was_clicked'                 => false,
                'was_converted'               => false,
                'cost_charged'                => $cost,
                'country_id'                  => $country->id,
                'device_type'                 => 'desktop',
                'shown_at'                    => now(),
            ]);

            if ($cost > 0 && $campaign) {
                $this->chargeCpmImpression($campaign, $listing->id, $country->id, $cost);
            }
        })->afterResponse();

        $variant  = $listing->productVariant;
        $product  = $variant?->product;
        $shipping = $listing->primaryShippingMethod;

        return [
            'impression_id'  => $impressionId,
            'listing_id'     => $listing->id,
            'campaign_id'    => $campaignId,
            'campaign_type'  => $listing->_campaign_type,
            'product_id'     => $product?->id,
            'product_slug'   => $product?->slug,
            'url_param'      => $variant?->id . '--' . $listing->id,
            'name'           => ['en' => $product?->name_en, 'ar' => $product?->name_ar],
            'thumbnail'      => $product?->images->first()?->url
                                ?? $variant?->images->first()?->url ?? null,
            'price'          => $listing->price,
            'currency'       => $country->currency_code,
            'shipping_badge' => $shipping ? [
                'label'             => ['en' => $shipping->badge_label_en, 'ar' => $shipping->badge_label_ar],
                'color_hex'         => $shipping->badge_color_hex,
                'text_color_hex'    => $shipping->badge_text_color_hex,
                'show_delivery_time' => (bool) $shipping->badge_show_delivery_time,
                'delivery_text' => ['ar' => $shipping->badge_delivery_text_resolved_ar, 'en' => $shipping->badge_delivery_text_resolved_en],
                'icon' => (($shipping->badge_icon ?? 'bolt') === 'none') ? null : ($shipping->badge_icon ?? 'bolt'),
                'badge_image_url' => $shipping->badge_image_url,
                'delivery_days_min' => $shipping->min_delivery_days,
                'delivery_days_max' => $shipping->max_delivery_days,
                'is_express'        => (bool) $shipping->is_express_type,
            ] : null,
            'is_express'     => (bool) ($shipping?->is_express_type ?? false),
        ];
    }

    /**
     * @param list<string> $categoryIds
     * @param array<string,list<string>> $attributeFilters
     */
    private function fetchSponsored(Country $country, int $limit, array $categoryIds = [], array $attributeFilters = []): Collection
    {
        $query = \App\Models\VendorListing::query()
            ->with([
                'vendor:id,store_name,store_rating_avg',
                'productVariant.images',
                'productVariant.product.images',
                'productVariant.product.category',
                'productVariant.product.brand',
                'productVariant.product.customAttributes',
                'primaryShippingMethod',
            ])
            ->join('ad_campaign_products as acp', 'acp.vendor_listing_id', '=', 'vendor_listings.id')
            ->join('ad_campaigns as ac', function ($j) use ($country) {
                $j->on('ac.id', '=', 'acp.ad_campaign_id')
                  ->where('ac.country_id', $country->id)
                  ->where('ac.status', 'active')
                  ->where(function ($q) {
                      $q->whereNull('ac.ends_at')->orWhere('ac.ends_at', '>', now());
                  });
            })
            ->where('vendor_listings.status', 'active')
            ->where('vendor_listings.country_id', $country->id)
            ->where('acp.is_active', true);

        // Only show sponsored products from the category being browsed, so an
        // empty category page never surfaces unrelated ads.
        if (!empty($categoryIds)) {
            $query->whereHas('productVariant.product', function ($q) use ($categoryIds) {
                $q->whereIn('category_id', $categoryIds);
            });
        }

        // Mirror ProductQueryService::applyFilters' attribute matching so sponsored
        // items respect the same attribute filters as organic results.
        foreach ($attributeFilters as $attrCode => $values) {
            $values = (array) $values;

            if (empty($values)) {
                continue;
            }

            $query->whereHas('productVariant.variantAttributes', function ($q) use ($attrCode, $values) {
                $q->whereHas('attribute', fn ($a) => $a->where('code', $attrCode))
                  ->whereHas('attributeValue', fn ($v) => $v->whereIn('value_en', $values));
            });
        }

        return $query
            ->orderByDesc('ac.quality_score')
            ->orderByDesc('ac.bid')
            ->limit($limit)
            ->get('vendor_listings.*');
    }

    private function recordImpression(
        \App\Models\VendorListing $listing,
        Country $country,
        string $placement,
        int $position,
        ?string $query,
    ): void {
        // Fire-and-forget — don't block response
        dispatch(function () use ($listing, $country, $placement, $position, $query) {
            $campaignProduct = \App\Models\AdCampaignProduct::where('vendor_listing_id', $listing->id)
                ->where('is_active', true)
                ->with('campaign')
                ->first();

            if (!$campaignProduct) {
                return;
            }

            $campaign = $campaignProduct->campaign;
            $cost     = $campaign ? $this->cpmCostForImpression($campaign) : 0;

            AdImpression::create([
                'id'                            => Str::uuid(),
                'ad_campaign_id'                => $campaignProduct->ad_campaign_id,
                'vendor_listing_id'             => $listing->id,
                'customer_id'                   => auth('customer')->id(),
                'session_id'                    => request()->hasSession() ? request()->session()->getId() : Str::random(26),
                'placement_code'                => $placement,
                'search_query'                  => $query,
                'position_shown'                => $position,
                'bid_at_impression'       => $campaignProduct->campaign->bid ?? 0,
                'quality_score_at_impression'   => $campaignProduct->campaign->quality_score ?? 0,
                'was_clicked'                   => false,
                'was_converted'                 => false,
                'cost_charged'            => $cost,
                'country_id'                    => $country->id,
                'device_type'                   => 'desktop',
                'shown_at'                      => now(),
            ]);

            if ($cost > 0 && $campaign) {
                $this->chargeCpmImpression($campaign, $listing->id, $country->id, $cost);
            }
        })->afterResponse();
    }

    /**
     * CPM billing: cost_per_impression = FLOOR(bid / 1000), charged at impression
     * time (unlike CPC, which charges at click time). Returns 0 for non-CPM
     * campaigns, a zero bid, or when charging would exceed the campaign's
     * remaining total/daily budget — in which case the impression is still
     * recorded (for reporting) but left unbilled, mirroring how the CPC click
     * path always records the click and only charges when cost > 0.
     */
    private function cpmCostForImpression(AdCampaign $campaign): int
    {
        if ($campaign->type !== AdCampaignType::Cpm) {
            return 0;
        }

        $cost = (int) floor($campaign->bid / 1000);

        if ($cost <= 0) {
            return 0;
        }

        if ($campaign->budget_spent_total + $cost > $campaign->budget_total) {
            return 0;
        }

        if ($campaign->budget_daily !== null && $campaign->budget_spent_today + $cost > $campaign->budget_daily) {
            return 0;
        }

        return $cost;
    }

    /**
     * Applies a CPM charge: increments the campaign's spend counters (same
     * atomic ->increment() convention the CPC click path uses) and rolls the
     * spend/impression count into today's ad_daily_stats row for this
     * campaign/listing/country.
     */
    private function chargeCpmImpression(AdCampaign $campaign, string $vendorListingId, string $countryId, int $cost): void
    {
        $campaign->increment('budget_spent_total', $cost);
        $campaign->increment('budget_spent_today', $cost);

        $stat = AdDailyStat::firstOrCreate([
            'ad_campaign_id'    => $campaign->id,
            'vendor_listing_id' => $vendorListingId,
            'country_id'        => $countryId,
            'date'              => now()->toDateString(),
        ]);

        $stat->increment('impressions');
        $stat->increment('spend', $cost);
    }
}
