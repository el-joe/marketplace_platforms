<?php

namespace App\Observers;

use App\Models\Country;
use App\Models\MarketerListing;
use App\Services\Customer\BuyBoxRebuildService;
use App\Support\ListingCacheVersion;

/**
 * enhancement.md P-19 task 2: marketer listings are the lowest-priority
 * buy-box candidate but still change min_price/max_price and the weighted
 * rating aggregate, so a price/status/rating change on one must resync
 * product_country_buybox too.
 */
class MarketerListingObserver
{
    public function __construct(private readonly BuyBoxRebuildService $buyBoxRebuilder)
    {
    }

    public function created(MarketerListing $listing): void
    {
        ListingCacheVersion::bump();
        $this->rebuildBuyBox($listing);
    }

    public function updated(MarketerListing $listing): void
    {
        if ($listing->wasChanged(['status', 'price', 'compare_at_price', 'score', 'rating_avg', 'rating_count'])) {
            ListingCacheVersion::bump();
            $this->rebuildBuyBox($listing);
        }
    }

    public function deleted(MarketerListing $listing): void
    {
        ListingCacheVersion::bump();
        $this->rebuildBuyBox($listing);
    }

    private function rebuildBuyBox(MarketerListing $listing): void
    {
        if ($listing->listing_category !== 'product' || ! $listing->product_variant_id) {
            return;
        }

        $productId = $listing->productVariant?->product_id;
        $country = $productId ? Country::find($listing->country_id) : null;

        if ($productId && $country) {
            $this->buyBoxRebuilder->rebuildProducts([$productId], $country);
        }
    }
}
