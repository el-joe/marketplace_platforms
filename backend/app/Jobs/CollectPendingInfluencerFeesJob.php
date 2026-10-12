<?php

namespace App\Jobs;

use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Retries the influencer platform fee for seller-paid invitations whose seller wallet
 * did not have enough balance when the influencer accepted.
 */
class CollectPendingInfluencerFeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketerCampaignService $service): void
    {
        MarketerCampaignInvitation::query()
            ->where('status', 'accepted')
            ->where('platform_fee_status', 'pending')
            ->where('platform_fee_payer', 'vendor')
            ->with('campaign')
            ->chunkById(100, function ($invitations) use ($service) {
                foreach ($invitations as $invitation) {
                    $service->collectInfluencerFee($invitation);
                }
            });
    }
}
