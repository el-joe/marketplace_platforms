<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminNewAdCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly AdCampaign $campaign) {}

    public function handle(): void
    {
        // TODO: send admin notification (in-app + email) when a new ad campaign
        // enters pending_review status and requires approval.
    }
}
