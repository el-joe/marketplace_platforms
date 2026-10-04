<?php

namespace App\Jobs;

use App\Models\MarketerCampaignConversion;
use App\Models\Wallet;
use App\Services\LedgerService;
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

    public function handle(LedgerService $ledgerService): void
    {
        MarketerCampaignConversion::where('status', 'pending')
            ->whereHas('orderItem.subOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->where(function ($q) {
                $q->whereHas('orderItem', fn ($i) => $i->whereNull('return_eligible_until'))
                    ->orWhereHas('orderItem', fn ($i) => $i->where('return_eligible_until', '<', today()));
            })
            ->with(['invitation', 'orderItem.subOrder'])
            ->chunkById(200, function ($conversions) use ($ledgerService) {
                foreach ($conversions as $conversion) {
                    $this->approveOne($conversion, $ledgerService);
                }
            });
    }

    private function approveOne(MarketerCampaignConversion $conversion, LedgerService $ledgerService): void
    {
        DB::transaction(function () use ($conversion, $ledgerService) {
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

            $platformCommission = (int) $locked->platform_commission_amount;
            if ($platformCommission > 0) {
                $vendorId = $locked->orderItem?->subOrder?->vendor_id;
                $ledgerService->record($ledgerService->newGroupId(), [
                    [
                        'account_type' => 'seller_payable',
                        'account_holder_type' => 'vendor',
                        'account_holder_id' => $vendorId,
                        'debit' => $platformCommission,
                        'credit' => 0,
                        'currency' => $locked->currency,
                        'reference_type' => 'marketer_conversion',
                        'reference_id' => (string) $locked->id,
                        'description' => "Platform commission deducted from vendor payable for conversion {$locked->id}",
                    ],
                    [
                        'account_type' => 'platform_commission',
                        'account_holder_type' => null,
                        'account_holder_id' => null,
                        'debit' => 0,
                        'credit' => $platformCommission,
                        'currency' => $locked->currency,
                        'reference_type' => 'marketer_conversion',
                        'reference_id' => (string) $locked->id,
                        'description' => "Platform commission earned on marketer conversion {$locked->id}",
                    ],
                ]);
            }
        });
    }
}
