<?php

namespace App\Services\Customer;

use App\Models\AdCampaign;
use App\Models\AdImpression;
use App\Models\Country;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SponsoredProductService
{
    private const SPONSORED_SLOTS = [1, 5, 9];

    /**
     * Inject sponsored products at fixed positions 1, 5, 9 (1-based) into a paginated items array.
     * Only injects on page 1.
     *
     * @param array $items         Already-paginated product items (from resource collection resolve)
     * @param Country $country
     * @param int $page
     * @param string $placement    e.g. 'search_results', 'category_top'
     * @param string|null $query
     * @return array
     */
    public function inject(
        array $items,
        Country $country,
        int $page,
        string $placement = 'search_results',
        ?string $query = null,
    ): array {
        if ($page !== 1) {
            return $items;
        }

        $sponsored = $this->fetchSponsored($country, count(self::SPONSORED_SLOTS));

        if ($sponsored->isEmpty()) {
            return $items;
        }

        foreach (self::SPONSORED_SLOTS as $position) {
            if ($sponsored->isEmpty()) {
                break;
            }

            $listing = $sponsored->shift();
            $product = $listing->productVariant->product ?? null;

            if (!$product) {
                continue;
            }

            $product->setAttribute('buy_box_listing_id', $listing->id);
            $product->setAttribute('buy_box_variant_slug', $listing->productVariant->slug);
            $product->setAttribute('buy_box_variant_name', $listing->productVariant->variant_name);

            $sponsoredItem = array_merge(
                (new \App\Http\Resources\Customer\ProductListResource($product))->resolve(),
                ['is_sponsored' => true, '_sponsored_listing_id' => $listing->id]
            );

            array_splice($items, $position - 1, 0, [$sponsoredItem]);

            $this->recordImpression($listing, $country, $placement, $position, $query);
        }

        return $items;
    }

    private function fetchSponsored(Country $country, int $limit): Collection
    {
        return \App\Models\VendorListing::query()
            ->with(['productVariant.product'])
            ->join('ad_campaign_products as acp', 'acp.vendor_listing_id', '=', 'vendor_listings.id')
            ->join('ad_campaigns as ac', function ($j) use ($country) {
                $j->on('ac.id', '=', 'acp.ad_campaign_id')
                  ->where('ac.country_id', $country->id)
                  ->where('ac.status', 'active')
                  ->whereNull('ac.ends_at')
                  ->orWhere('ac.ends_at', '>', now());
            })
            ->where('vendor_listings.status', 'active')
            ->where('vendor_listings.country_id', $country->id)
            ->where('acp.is_active', true)
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
            $campaign = \App\Models\AdCampaignProduct::where('vendor_listing_id', $listing->id)
                ->where('is_active', true)
                ->with('campaign')
                ->first();

            if (!$campaign) {
                return;
            }

            AdImpression::create([
                'id'                            => Str::uuid(),
                'ad_campaign_id'                => $campaign->ad_campaign_id,
                'vendor_listing_id'             => $listing->id,
                'customer_id'                   => auth('customer')->id(),
                'session_id'                    => request()->hasSession() ? request()->session()->getId() : Str::random(26),
                'placement_code'                => $placement,
                'search_query'                  => $query,
                'position_shown'                => $position,
                'bid_at_impression'       => $campaign->campaign->bid ?? 0,
                'quality_score_at_impression'   => $campaign->campaign->quality_score ?? 0,
                'was_clicked'                   => false,
                'was_converted'                 => false,
                'cost_charged'            => 0,
                'country_id'                    => $country->id,
                'device_type'                   => 'desktop',
                'shown_at'                      => now(),
            ]);
        })->afterResponse();
    }
}
