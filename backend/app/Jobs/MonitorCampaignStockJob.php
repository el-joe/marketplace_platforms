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

    public function handle(MarketerCampaignService $service): void
    {
        MarketerCampaign::whereIn('status', ['active', 'auto_approved'])
            ->with(['vendorListing.warehouseInventories'])
            ->get()
            ->each(function (MarketerCampaign $campaign) use ($service) {
                $outOfStock = false;
                if ($campaign->vendor_listing_id && $campaign->vendorListing) {
                    $stock = (int) $campaign->vendorListing->warehouseInventories->sum('quantity_available');
                    $outOfStock = $stock <= 0;
                }
                if ($outOfStock) {
                    $service->markCampaignDone($campaign);
                }
            });
    }
}
