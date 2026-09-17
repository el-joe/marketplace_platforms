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
 * enhancement.md P-12 task 3: daily job approving 'pending' conversions
 * once BOTH of these hold for their order item:
 *   - the sub-order has been delivered (or completed);
 *   - the item's return window has passed (return_eligible_until < today,
 *     or null — no returnable item on this order at all).
 * On approval, the commission (base + flash-sale bonus) is credited to the
 * marketer's wallet as pending_balance — not yet spendable, released into
 * `balance` by ReleaseMarketerPendingCommissionJob after the
 * `marketer_payout_clearing_days` setting (default 3) has also passed, a
 * short extra hold on top of the return window so a very-late RTO/dispute
 * doesn't have to claw back money the marketer already withdrew.
 *
 * Mirrors CaptureCodOnDelivery/ExpireWarrantyPurchasesJob's pattern: this
 * runs on a schedule rather than directly off SubOrderDelivered, because
 * "return window passed" is a date condition that becomes true well after
 * the delivery event fires, not at delivery time itself.
 */
class ApproveMarketerConversionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        MarketerCampaignConversion::where('status', 'pending')
            ->whereHas('orderItem.subOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->where(function ($q) {
                $q->whereHas('orderItem', fn ($i) => $i->whereNull('return_eligible_until'))
                    ->orWhereHas('orderItem', fn ($i) => $i->where('return_eligible_until', '<', today()));
            })
            ->with('invitation')
            ->chunkById(200, function ($conversions) {
                foreach ($conversions as $conversion) {
                    $this->approveOne($conversion);
                }
            });
    }

    private function approveOne(MarketerCampaignConversion $conversion): void
    {
        DB::transaction(function () use ($conversion) {
            $locked = MarketerCampaignConversion::where('id', $conversion->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return;
            }

            $marketerId = $locked->invitation?->marketer_id;
            if (! $marketerId) {
                return;
            }

            $totalCommission = (int) $locked->commission_amount + (int) ($locked->flash_sale_bonus_amount ?? 0);

            $wallet = Wallet::firstOrCreate(
                ['owner_type' => 'marketer', 'owner_id' => $marketerId],
                ['balance' => 0, 'pending_balance' => 0, 'currency' => $locked->currency]
            );

            Wallet::where('id', $wallet->id)->increment('pending_balance', $totalCommission);

            $locked->update([
                'status' => 'approved',
                'approved_at' => now(),
                'wallet_credited_at' => now(),
            ]);
        });
    }
}
