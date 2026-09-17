<?php

namespace App\Listeners;

use App\Events\ListingStockChanged;
use App\Services\Marketer\MarketerListingAvailabilityService;

/**
 * enhancement.md P-15 task 4: a marketer listing must become unavailable
 * the moment its OWN source becomes unavailable (including stock hitting
 * zero), independent of whether the parent campaign is still active
 * (that's P-14's Listeners\PauseCampaignsOnLowStock, a separate concern).
 *
 * Registered after Listeners\SyncListingStockStatus for the same event, so
 * by the time this runs the vendor/admin listing's `status` column already
 * reflects the new stock level (active <-> out_of_stock) — we just re-read
 * it. All synchronous, no queue, so the storefront sees the change within
 * the same request.
 */
class SyncMarketerListingAvailabilityOnStock
{
    public function __construct(
        private readonly MarketerListingAvailabilityService $marketerAvailability,
    ) {
    }

    public function handle(ListingStockChanged $event): void
    {
        if ($event->vendorListingId) {
            $this->marketerAvailability->syncAllForSource('vendor_listing', $event->vendorListingId);
        }

        if ($event->adminListingId) {
            $this->marketerAvailability->syncAllForSource('admin_listing', $event->adminListingId);
        }
    }
}
