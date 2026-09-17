<?php

namespace App\Observers;

use App\Jobs\RecomputeListingShippingMethodsJob;
use App\Models\VendorListing;
use App\Services\CachedListingResolver;
use App\Services\Marketer\MarketerListingAvailabilityService;
use App\Services\Shared\PageCacheService;

class VendorListingObserver
{
    public function __construct(
        private readonly CachedListingResolver $cachedListingResolver,
        private readonly PageCacheService $pageCache,
        private readonly MarketerListingAvailabilityService $marketerAvailability,
    ) {
    }

    public function created(VendorListing $listing): void
    {
        dispatch(new RecomputeListingShippingMethodsJob(
            [$listing->productVariant->product->category_id]
        ));

        $this->cachedListingResolver->bustVendorListing($listing);
    }

    public function updated(VendorListing $listing): void
    {
        if ($listing->wasChanged('global_system_type')) {
            dispatch(new RecomputeListingShippingMethodsJob(
                [$listing->productVariant->product->category_id]
            ));
        }

        if ($listing->wasChanged(['status', 'price', 'score', 'rating_avg', 'rating_count',
                                   'primary_shipping_method_id', 'vendor_covers_delivery'])) {
            $this->cachedListingResolver->bustVendorListing($listing);
            $this->pageCache->bustVendorListing($listing);
        }

        // enhancement.md P-15 task 4: keep marketer listings sourced from
        // this vendor listing in sync (status/price), independent of P-14's
        // campaign-level pausing.
        if ($listing->wasChanged(['status', 'price'])) {
            $this->marketerAvailability->syncAllForSource('vendor_listing', $listing->id);
        }
    }

    public function deleted(VendorListing $listing): void
    {
        $this->cachedListingResolver->bustVendorListing($listing);
        $this->pageCache->bustVendorListing($listing);
    }
}
