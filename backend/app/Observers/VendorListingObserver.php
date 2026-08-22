<?php

namespace App\Observers;

use App\Jobs\RecomputeListingShippingMethodsJob;
use App\Models\VendorListing;
use App\Services\CachedListingResolver;
use App\Services\Shared\PageCacheService;

class VendorListingObserver
{
    public function __construct(
        private readonly CachedListingResolver $cachedListingResolver,
        private readonly PageCacheService $pageCache,
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
    }

    public function deleted(VendorListing $listing): void
    {
        $this->cachedListingResolver->bustVendorListing($listing);
        $this->pageCache->bustVendorListing($listing);
    }
}
