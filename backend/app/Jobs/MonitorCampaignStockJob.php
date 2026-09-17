<?php

namespace App\Jobs;

use App\Models\MarketerCampaign;
use App\Services\MarketerCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorCampaignStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * enhancement.md P-14 task 4: scheduled safety-net sweep. The
     * ListingStockChanged listener (PauseCampaignsOnLowStock) already
     * reacts immediately to every stock mutation; this hourly sweep
     * (routes/console.php) catches anything that listener might have
     * missed, and now also covers admin-listing (platform) campaigns and
     * the below-min_stock_for_campaign "pause" case, not just the
     * zero-stock "done" case.
     */
    public function handle(MarketerCampaignService $service): void
    {
        MarketerCampaign::whereIn('status', ['active', 'auto_approved', 'paused'])
            ->where(function ($q) {
                $q->whereNotNull('vendor_listing_id')->orWhereNotNull('admin_listing_id');
            })
            ->with(['vendorListing.warehouseInventories', 'adminListing.warehouseInventories'])
            ->get()
            ->each(fn (MarketerCampaign $campaign) => $service->checkStockAndUpdateStatus($campaign));
    }
}
