<?php

namespace App\Jobs;

use App\Models\MarketerCampaignConversion;
use App\Services\Marketer\MarketerCommissionPostingService;
use App\Services\Marketer\MarketerSaleSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Moves an approved conversion's NET commission from the marketer wallet's pending_balance into the spendable balance
 * once `marketer_payout_clearing_days` (default 3) have passed since approval, for every conversion type.
 *
 * The release is where the money becomes real, so it also posts the double-entry ledger group
 * (vendor budget -> marketer net + platform commission fee) and writes the wallet transaction line; see
 * MarketerCommissionPostingService::release(). The commission fee was snapshotted when the sale was recorded.
 */
class ReleaseMarketerPendingCommissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DEFAULT_CLEARING_DAYS = 3;

    public function handle(MarketerCommissionPostingService $postings): void
    {
        $clearingDays = (int) setting('marketer_payout_clearing_days', self::DEFAULT_CLEARING_DAYS);

        MarketerCampaignConversion::where('status', 'approved')
            ->whereNotNull('wallet_credited_at')
            ->whereNull('wallet_released_at')
            ->where('approved_at', '<=', now()->subDays($clearingDays))
            ->with(['invitation', 'campaign'])
            ->chunkById(200, function ($conversions) use ($postings) {
                foreach ($conversions as $conversion) {
                    $this->releaseOne($conversion, $postings);
                }
            });

        // Sales of marketer-owned listings (seller_type = marketer) clear on the same schedule.
        app(MarketerSaleSettlementService::class)->releaseDue($clearingDays);
    }

    private function releaseOne(MarketerCampaignConversion $conversion, MarketerCommissionPostingService $postings): void
    {
        DB::transaction(function () use ($conversion, $postings) {
            $locked = MarketerCampaignConversion::where('id', $conversion->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'approved' || $locked->wallet_released_at) {
                return;
            }

            $marketerId = $locked->invitation?->marketer_id;
            if (! $marketerId) {
                return;
            }

            $postings->release($locked, $marketerId);
            $locked->update(['wallet_released_at' => now()]);
        });
    }
}
