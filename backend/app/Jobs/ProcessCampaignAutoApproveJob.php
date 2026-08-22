<?php

namespace App\Jobs;

use App\Services\MarketerCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCampaignAutoApproveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $campaignId)
    {
    }

    public function handle(MarketerCampaignService $service): void
    {
        $service->autoApproveCampaign($this->campaignId);
    }
}
