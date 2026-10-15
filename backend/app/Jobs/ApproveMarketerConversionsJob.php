<?php

namespace App\Jobs;

use App\Models\MarketerCampaignConversion;
use App\Services\Marketer\MarketerCommissionPostingService;
use App\Services\Marketer\MarketerEarningsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Daily job approving 'pending' conversions once the thing they were earned on is final, per conversion_type:
 *
 *   product_sale      the sub-order is delivered / completed and the item's return window has passed;
 *   classified_sale   `marketer_classified_approval_days` (default 2) have passed since the sale was triggered;
 *   travel_booking    the booking is completed (travel_bookings.completed_at);
 *   classified_inquiry never auto-approved (a lead is not a sale).
 *
 * Approval parks the conversion's NET commission (commission fee already taken out when the sale was recorded) in the
 * marketer wallet's pending_balance. ReleaseMarketerPendingCommissionJob moves it to the spendable balance after
 * `marketer_payout_clearing_days` and posts the wallet / ledger entries.
 */
class ApproveMarketerConversionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DEFAULT_CLASSIFIED_APPROVAL_DAYS = 2;

    public function handle(MarketerCommissionPostingService $postings, MarketerEarningsService $earnings): void
    {
        MarketerCampaignConversion::where('status', 'pending')
            ->where(fn (Builder $q) => $this->eligible($q))
            ->with(['invitation'])
            ->chunkById(200, function ($conversions) use ($postings, $earnings) {
                foreach ($conversions as $conversion) {
                    $this->approveOne($conversion, $postings, $earnings);
                }
            });
    }

    /** Restrict a conversion query to rows whose source is final. */
    private function eligible(Builder $query): void
    {
        $classifiedDays = max(0, (int) setting('marketer_classified_approval_days', self::DEFAULT_CLASSIFIED_APPROVAL_DAYS));

        $query
            ->where(function (Builder $q) {
                $q->where(fn (Builder $p) => $p->whereNull('conversion_type')->orWhere('conversion_type', MarketerCampaignConversion::TYPE_PRODUCT_SALE))
                    ->whereHas('orderItem.subOrder', fn ($s) => $s->whereIn('status', ['delivered', 'completed']))
                    ->where(function ($w) {
                        $w->whereHas('orderItem', fn ($i) => $i->whereNull('return_eligible_until'))
                            ->orWhereHas('orderItem', fn ($i) => $i->where('return_eligible_until', '<', today()));
                    });
            })
            ->orWhere(function (Builder $q) use ($classifiedDays) {
                $q->where('conversion_type', MarketerCampaignConversion::TYPE_CLASSIFIED_SALE)
                    ->whereNotNull('triggered_at')
                    ->where('triggered_at', '<=', now()->subDays($classifiedDays));
            })
            ->orWhere(function (Builder $q) {
                $q->where('conversion_type', MarketerCampaignConversion::TYPE_TRAVEL_BOOKING)
                    ->whereHas('travelBooking', fn ($b) => $b->whereNotNull('completed_at'));
            });
    }

    private function approveOne(MarketerCampaignConversion $conversion, MarketerCommissionPostingService $postings, MarketerEarningsService $earnings): void
    {
        DB::transaction(function () use ($conversion, $postings, $earnings) {
            $locked = MarketerCampaignConversion::where('id', $conversion->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return;
            }

            $marketerId = $locked->invitation?->marketer_id;
            if (! $marketerId) {
                return;
            }

            // Conversions recorded before v3 carry no fee snapshot: take it now, once.
            $snapshot = $locked->commission_fee_amount === null ? $earnings->snapshotAttributes($locked) : [];

            $locked->fill($snapshot + [
                'status' => 'approved',
                'approved_at' => now(),
                'wallet_credited_at' => now(),
            ]);
            $postings->creditPending($locked, $marketerId);
            $locked->save();
        });
    }
}
