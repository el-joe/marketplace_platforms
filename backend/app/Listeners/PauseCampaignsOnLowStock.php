<?php

namespace App\Listeners;

use App\Events\ListingStockChanged;
use App\Models\MarketerCampaign;
use App\Services\MarketerCampaignService;

/**
 * enhancement.md P-14 task 4: react to ListingStockChanged (fired by
 * InventoryService, P-13) immediately rather than waiting for
 * MonitorCampaignStockJob's hourly sweep — pauses/resumes/completes any
 * live campaign sourced from the listing whose stock just changed.
 * The scheduled sweep (routes/console.php) stays as a safety net for any
 * campaign this event misses.
 */
class PauseCampaignsOnLowStock
{
    public function handle(ListingStockChanged $event): void
    {
        $service = app(MarketerCampaignService::class);

        $campaigns = MarketerCampaign::whereIn('status', ['active', 'auto_approved', 'paused'])
            ->when($event->vendorListingId, fn ($q) => $q->where('vendor_listing_id', $event->vendorListingId))
            ->when($event->adminListingId, fn ($q) => $q->where('admin_listing_id', $event->adminListingId))
            ->when(!$event->vendorListingId && !$event->adminListingId, fn ($q) => $q->whereRaw('1 = 0'))
            ->get();

        foreach ($campaigns as $campaign) {
            $service->checkStockAndUpdateStatus($campaign);
        }
    }
}
