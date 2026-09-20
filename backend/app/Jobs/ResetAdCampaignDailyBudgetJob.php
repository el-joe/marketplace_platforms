<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily reset of ad_campaigns.budget_spent_today at midnight. Without this,
 * a campaign that hits its budget_daily cap on day 1 stays capped forever —
 * both the CPC click path and CPM impression billing check
 * budget_spent_today against budget_daily to decide whether to keep
 * charging/showing ads for a campaign.
 */
class ResetAdCampaignDailyBudgetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        AdCampaign::where('budget_spent_today', '>', 0)->update(['budget_spent_today' => 0]);
    }
}
