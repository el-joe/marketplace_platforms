<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * enhancement.md P-12 task 3: reverse a marketer conversion (and any wallet
 * credit already given for it) when the order item it was earned on is
 * cancelled or returned. Shared by OrderCancellationService (full/partial
 * order cancel) and RefundService (item-level return refunds) — both used
 * to have no-op or missing handling for this.
 *
 * A conversion already 'paid' (included in a settled marketer payout) is
 * NEVER clawed back automatically here — that money has left the
 * platform's control; recovering it is a manual/admin process, not this
 * service's job (same policy P-07/P-11 use for vendor payouts).
 */
class MarketerConversionReversalService
{
    public function __construct(private readonly LedgerService $ledgerService) {}

    /**
     * @param  array<int, string>  $orderItemIds
     */
    public function reverseForOrderItemIds(array $orderItemIds): void
    {
        if (empty($orderItemIds)) {
            return;
        }

        $conversions = MarketerCampaignConversion::whereIn('order_item_id', $orderItemIds)
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        foreach ($conversions as $conversion) {
            $this->reverseOne($conversion);
        }
    }

    public function reverseOne(MarketerCampaignConversion $conversion): void
    {
        DB::transaction(function () use ($conversion) {
            /** @var MarketerCampaignConversion $conversion */
            $conversion = MarketerCampaignConversion::where('id', $conversion->id)->lockForUpdate()->first();

            if (! $conversion || ! in_array($conversion->status, ['pending', 'approved'], true)) {
                return; // already reversed/paid, or gone — idempotent no-op.
            }

            $totalCommission = (int) $conversion->commission_amount + (int) ($conversion->flash_sale_bonus_amount ?? 0);

            // Claw back whatever wallet credit was already given.
            if ($conversion->wallet_credited_at) {
                $marketerId = $conversion->invitation?->marketer_id;
                $wallet = $marketerId
                    ? Wallet::where('owner_type', 'marketer')->where('owner_id', $marketerId)->lockForUpdate()->first()
                    : null;

                if ($wallet) {
                    if ($conversion->wallet_released_at) {
                        // Already moved from pending_balance into the spendable
                        // balance — claw back from there (may go negative if
                        // the marketer already withdrew; that's a receivable,
                        // out of scope for this service — logged for finance).
                        $wallet->decrement('balance', $totalCommission);
                        if ($wallet->balance < 0) {
                            Log::warning('Marketer wallet balance went negative reversing a paid-out conversion.', [
                                'wallet_id' => $wallet->id,
                                'conversion_id' => $conversion->id,
                            ]);
                        }
                    } else {
                        $wallet->decrement('pending_balance', $totalCommission);
                    }
                }
            }

            // Reverse the platform commission ledger entry posted when the conversion
            // was approved. Only approved conversions have a ledger entry; pending
            // conversions were never approved so no ledger entry exists to reverse.
            if ($conversion->wallet_credited_at && (int) $conversion->platform_commission_amount > 0) {
                $original = LedgerEntry::where('reference_type', 'marketer_conversion')
                    ->where('reference_id', $conversion->id)
                    ->get();

                if ($original->isNotEmpty()) {
                    $reversalEntries = $original->map(fn (LedgerEntry $e) => [
                        'account_type' => $e->account_type,
                        'account_holder_type' => $e->account_holder_type,
                        'account_holder_id' => $e->account_holder_id,
                        'debit' => (int) $e->credit,
                        'credit' => (int) $e->debit,
                        'currency' => $e->currency,
                        'reference_type' => 'marketer_conversion_reversal',
                        'reference_id' => (string) $conversion->id,
                        'description' => "Reversal of platform commission for conversion {$conversion->id}",
                    ])->all();

                    $this->ledgerService->record($this->ledgerService->newGroupId(), $reversalEntries);
                }
            }

            // Free up the campaign's committed budget.
            $campaign = MarketerCampaign::where('id', $conversion->campaign_id)->lockForUpdate()->first();
            if ($campaign) {
                $campaign->decrement('commission_budget_spent', $totalCommission);
            }

            $conversion->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'commissioned' => false,
                'paid_at' => null,
            ]);
        });
    }
}
