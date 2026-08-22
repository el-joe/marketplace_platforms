<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CampaignApprovedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly AdCampaign $campaign)
    {
    }

    public function handle(): void
    {
        // TODO: notify vendor that campaign has been approved
        // e.g. Mail::to($this->campaign->vendor->owner)->send(new CampaignApprovedMail($this->campaign));
    }
}
