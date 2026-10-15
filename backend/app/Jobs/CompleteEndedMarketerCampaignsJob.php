<?php

namespace App\Jobs;

use App\Services\MarketerCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily sweep: a marketer campaign whose end date (`ends_at`) has passed is completed. Completion pays every
 * influencer still on the campaign his fixed amount (net of the commission fee) and releases the reservation of
 * the others back to the budget; see MarketerCampaignService::markCampaignDone().
 */
class CompleteEndedMarketerCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketerCampaignService $service): void
    {
        $service->completeEndedCampaigns();
    }
}
