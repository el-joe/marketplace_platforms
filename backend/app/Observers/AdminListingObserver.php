<?php

namespace App\Observers;

use App\Models\AdminListing;
use App\Services\CachedListingResolver;
use App\Services\Shared\PageCacheService;

class AdminListingObserver
{
    public function __construct(
        private readonly CachedListingResolver $cachedListingResolver,
        private readonly PageCacheService $pageCache,
    ) {
    }

    public function created(AdminListing $listing): void
    {
        $this->cachedListingResolver->bustAdminListing($listing);
    }

    public function updated(AdminListing $listing): void
    {
        if ($listing->wasChanged(['status', 'price', 'score', 'rating_avg', 'rating_count',
                                   'primary_shipping_method_id', 'vendor_covers_delivery',
                                   'sold_by_label_en', 'sold_by_label_ar',
                                   'express_badge_label_en', 'express_badge_label_ar'])) {
            $this->cachedListingResolver->bustAdminListing($listing);
            $this->pageCache->bustAdminListing($listing);
        }
    }

    public function deleted(AdminListing $listing): void
    {
        $this->cachedListingResolver->bustAdminListing($listing);
        $this->pageCache->bustAdminListing($listing);
    }
}
