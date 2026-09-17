<?php

namespace App\Observers;

use App\Models\AdminListing;
use App\Services\CachedListingResolver;
use App\Services\Marketer\MarketerListingAvailabilityService;
use App\Services\Shared\PageCacheService;

class AdminListingObserver
{
    public function __construct(
        private readonly CachedListingResolver $cachedListingResolver,
        private readonly PageCacheService $pageCache,
        private readonly MarketerListingAvailabilityService $marketerAvailability,
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

        // enhancement.md P-15 task 4.
        if ($listing->wasChanged(['status', 'price'])) {
            $this->marketerAvailability->syncAllForSource('admin_listing', $listing->id);
        }
    }

    public function deleted(AdminListing $listing): void
    {
        $this->cachedListingResolver->bustAdminListing($listing);
        $this->pageCache->bustAdminListing($listing);
    }
}
