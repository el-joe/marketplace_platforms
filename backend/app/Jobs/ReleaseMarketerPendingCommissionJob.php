<?php

namespace App\Jobs;

use App\Models\MarketerCampaignConversion;
use App\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-12 task 3: moves an approved conversion's commission
 * from the marketer wallet's pending_balance into the spendable balance
 * once `marketer_payout_clearing_days` (default 3) have passed since
 * approval — see ApproveMarketerConversionsJob's docblock for why this is
 * a second, shorter hold on top of the return window.
 */
class ReleaseMarketerPendingCommissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DEFAULT_CLEARING_DAYS = 3;

    public function handle(): void
    {
        $clearingDays = (int) setting('marketer_payout_clearing_days', self::DEFAULT_CLEARING_DAYS);

        MarketerCampaignConversion::where('status', 'approved')
            ->whereNotNull('wallet_credited_at')
            ->whereNull('wallet_released_at')
            ->where('approved_at', '<=', now()->subDays($clearingDays))
            ->with('invitation')
            ->chunkById(200, function ($conversions) {
                foreach ($conversions as $conversion) {
                    $this->releaseOne($conversion);
                }
            });
    }

    private function releaseOne(MarketerCampaignConversion $conversion): void
    {
        DB::transaction(function () use ($conversion) {
            $locked = MarketerCampaignConversion::where('id', $conversion->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'approved' || $locked->wallet_released_at) {
                return;
            }

            $marketerId = $locked->invitation?->marketer_id;
            if (! $marketerId) {
                return;
            }

            $totalCommission = (int) $locked->commission_amount + (int) ($locked->flash_sale_bonus_amount ?? 0);

            $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $marketerId)->lockForUpdate()->first();
            if ($wallet) {
                Wallet::where('id', $wallet->id)->decrement('pending_balance', $totalCommission);
                Wallet::where('id', $wallet->id)->increment('balance', $totalCommission);
            }

            $locked->update(['wallet_released_at' => now()]);
        });
    }
}
