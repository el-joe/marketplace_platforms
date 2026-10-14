<?php

namespace App\Jobs;

use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerFeePayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Credits influencer fee shares whose vendor charge is paid but whose wallet credit did not happen yet
 * (frozen marketer wallet, transient failure). Idempotent: the service skips already credited rows.
 */
class RetryMarketerFeeCreditsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketerFeePayoutService $service): void
    {
        MarketerCampaignInvitation::query()
            ->where('platform_fee_status', 'paid')
            ->where('platform_fee_payer', 'vendor')
            ->where('marketer_payout_amount', '>', 0)
            ->whereNull('marketer_credited_at')
            ->whereNull('marketer_payout_wallet_tx_id')
            ->chunkById(100, function ($invitations) use ($service) {
                foreach ($invitations as $invitation) {
                    $service->creditForInvitation($invitation);
                }
            });
    }
}
