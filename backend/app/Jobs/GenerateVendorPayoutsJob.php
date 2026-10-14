<?php

namespace App\Jobs;

use App\Enums\FbnStorageFeeStatus;
use App\Enums\VendorGlobalStatus;
use App\Enums\VendorSubscriptionInvoiceStatus;
use App\Models\Admin;
use App\Models\FbnDailyOverageFee;
use App\Models\FbnStorageFee;
use App\Models\MarketerCampaignFeeSettlement;
use App\Models\MarketerCampaignInvitation;
use App\Models\PackagingSupplyRequest;
use App\Models\PaidAdCharge;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use App\Models\VendorSubscriptionInvoice;
use App\Notifications\Admin\PayoutBatchReadyForApproval;
use App\Services\MarketerFeePayoutService;
use App\Services\PayoutCalculationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GenerateVendorPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  Carbon  $periodStart  Start of the payout period (inclusive)
     * @param  Carbon  $periodEnd  End of the payout period (inclusive)
     * @param  string|null  $scheduleFilter  When set (enhancement.md P-11 task 3), only
     *                                       run for vendors whose `payout_schedule` matches this value ('weekly',
     *                                       'biweekly', 'monthly'), so routes/console.php can dispatch one run per
     *                                       cadence with the right period for that cadence. Null (default) processes
     *                                       every active vendor regardless of schedule, preserving prior behaviour
     *                                       for manual/on-demand runs.
     */
    public function __construct(
        private readonly Carbon $periodStart,
        private readonly Carbon $periodEnd,
        private readonly ?string $scheduleFilter = null
    ) {}

    public function handle(PayoutCalculationService $calculationService): void
    {
        $generated = 0;

        Vendor::where('global_status', VendorGlobalStatus::Active)
            ->when($this->scheduleFilter !== null, fn ($q) => $q->where('payout_schedule', $this->scheduleFilter))
            ->whereHas('bankAccounts', fn ($q) => $q->where('is_primary', true)
                ->where('verification_status', 'verified'))
            ->chunkById(50, function ($vendors) use ($calculationService, &$generated) {
                foreach ($vendors as $vendor) {
                    // Skip if a payout already exists for this vendor/period
                    $exists = Payout::where('vendor_id', $vendor->id)
                        ->where('period_start', $this->periodStart->toDateString())
                        ->where('period_end', $this->periodEnd->toDateString())
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $primaryAccount = VendorBankAccount::where('vendor_id', $vendor->id)
                        ->where('is_primary', true)
                        ->where('verification_status', 'verified')
                        ->first();

                    if (! $primaryAccount) {
                        continue;
                    }

                    // Returns one entry per currency found in payable sub-orders.
                    $calcByCurrency = $calculationService->calculateForVendor(
                        $vendor,
                        $this->periodStart,
                        $this->periodEnd
                    );

                    foreach ($calcByCurrency as $currency => $calc) {
                        if ($calc['net_amount'] <= 0) {
                            continue;
                        }

                        // Skip if a payout for this vendor/period/currency already exists.
                        $exists = Payout::where('vendor_id', $vendor->id)
                            ->where('period_start', $this->periodStart->toDateString())
                            ->where('period_end', $this->periodEnd->toDateString())
                            ->where('currency', $currency)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $paidInvitations = [];
                        DB::transaction(function () use ($calc, $currency, $vendor, $primaryAccount, &$paidInvitations) {
                            // Influencer fees: lock each invitation and re-check it is still
                            // `pending` (the wallet collector may have paid it since the
                            // calculation). A fee that is no longer pending is not charged here.
                            $feeLines = [];
                            foreach ($calc['influencer_fee_items'] ?? [] as $entry) {
                                $locked = MarketerCampaignInvitation::whereKey($entry['item']->id)->lockForUpdate()->first();
                                if ($locked
                                    && $locked->platform_fee_status === 'pending'
                                    && ! $locked->payoutItems()->exists()) {
                                    $feeLines[] = [
                                        'invitation' => $locked,
                                        'fee' => (float) $locked->platform_fee_amount,
                                        'vat' => (float) $locked->platform_fee_vat_amount,
                                        'marketing' => (float) $locked->platform_fee_marketing_amount,
                                    ];
                                }
                            }
                            // Affiliate marketing-fee settlements: same lock + re-check.
                            $affiliateLines = [];
                            foreach ($calc['affiliate_fee_items'] ?? [] as $entry) {
                                $lockedSettlement = MarketerCampaignFeeSettlement::whereKey($entry['item']->id)->lockForUpdate()->first();
                                if ($lockedSettlement
                                    && $lockedSettlement->status === MarketerCampaignFeeSettlement::STATUS_PENDING
                                    && ! $lockedSettlement->payoutItems()->exists()) {
                                    $affiliateLines[] = $lockedSettlement;
                                }
                            }
                            $skippedAffiliate = round((float) ($calc['affiliate_fees'] ?? 0) - array_sum(array_map(fn ($l) => (float) $l->amount, $affiliateLines)), 2);

                            $skipped = round((float) $calc['influencer_fees'] - array_sum(array_map(fn ($l) => $l['fee'] + $l['vat'] + $l['marketing'], $feeLines)) + $skippedAffiliate, 2);
                            if (abs($skipped) > 0) {
                                $calc['net_amount'] = round($calc['net_amount'] + $skipped, 2);
                                $calc['other_adjustments'] = round($calc['other_adjustments'] + $skipped, 2);
                            }

                            $payout = Payout::create([
                                'payout_number' => $this->generatePayoutNumber(),
                                'vendor_id' => $vendor->id,
                                'period_start' => $calc['period_start'],
                                'period_end' => $calc['period_end'],
                                'gross_sales' => $calc['gross_sales'],
                                'commission' => $calc['commission'],
                                'gateway_fee_deducted' => $calc['gateway_fee_deducted'],
                                'refunds_deducted' => $calc['refunds_deducted'],
                                'chargebacks_deducted' => $calc['chargebacks_deducted'],
                                'storage_fees' => $calc['storage_fees'],
                                'ad_fees' => $calc['ad_fees'],
                                'other_adjustments' => $calc['other_adjustments'],
                                'net_amount' => $calc['net_amount'],
                                'currency' => $currency, // sales currency — see PayoutCalculationService note on FX
                                'status' => 'pending',
                                'bank_account_id' => $primaryAccount->id,
                                'payout_method' => 'bank_transfer',
                            ]);

                            // enhancement.md P-11: one payout_items row per sub-order
                            // paid, so the NEXT payout run's whereDoesntHave('payoutItems')
                            // guard in PayoutCalculationService can exclude it — this is
                            // the double-pay fix.
                            $subOrders = SubOrder::whereIn('id', $calc['sub_order_ids'] ?? [])->get();
                            foreach ($subOrders as $subOrder) {
                                PayoutItem::create([
                                    'payout_id' => $payout->id,
                                    'item_type' => 'sub_order',
                                    'sub_order_id' => $subOrder->id,
                                    'gross' => round((float) $subOrder->subtotal, 2),
                                    'commission' => round((float) $subOrder->platform_commission, 2),
                                    'net' => round((float) $subOrder->vendor_payout, 2),
                                ]);
                            }

                            if (! empty($calc['storage_fee_ids'])) {
                                FbnStorageFee::whereIn('id', $calc['storage_fee_ids'])
                                    ->update(['status' => FbnStorageFeeStatus::Invoiced]);
                            }
                            if (! empty($calc['overage_fee_ids'])) {
                                FbnDailyOverageFee::whereIn('id', $calc['overage_fee_ids'])
                                    ->update(['status' => FbnStorageFeeStatus::Invoiced]);
                            }
                            if (! empty($calc['packaging_request_ids'])) {
                                PackagingSupplyRequest::whereIn('id', $calc['packaging_request_ids'])
                                    ->update(['fee_deducted_at' => now()]);
                            }
                            if (! empty($calc['subscription_invoice_ids'])) {
                                VendorSubscriptionInvoice::whereIn('id', $calc['subscription_invoice_ids'])
                                    ->update(['status' => VendorSubscriptionInvoiceStatus::Paid, 'paid_at' => now()]);
                            }

                            foreach ($calc['promotion_fee_requests'] ?? [] as $request) {
                                $listing = $request->vendorListing ?? $request->adminListing;
                                $product = $listing?->productVariant?->product;
                                $productName = $product?->name_en ?? $product?->name_ar ?? '—';
                                $slots = $request->items->count();
                                $feePerSlot = $request->items->first()?->slot_promotion_fee ?? 0;

                                PayoutItem::create([
                                    'payout_id' => $payout->id,
                                    'item_type' => 'promotion_fee',
                                    'promotion_request_id' => $request->id,
                                    'gross' => 0,
                                    'commission' => 0,
                                    'net' => -$request->total_promotion_fee,
                                    'description' => "Influencer Promotion Fee — {$productName} — {$slots} slots × {$feePerSlot}",
                                ]);

                                $request->update([
                                    'fee_deducted' => true,
                                    'fee_deducted_at' => now(),
                                ]);
                            }

                            // Promotion samples: price 0 for the seller, only the admin
                            // sale commission is deducted. campaign_sample_id is unique, so
                            // a sample can never appear on two statements.
                            foreach ($calc['sample_items'] ?? [] as $entry) {
                                $sample = $entry['item'];
                                $product = $sample->campaign?->vendorListing?->productVariant?->product;
                                $productName = $product?->name_en ?? $product?->name_ar ?? '—';

                                PayoutItem::create([
                                    'payout_id' => $payout->id,
                                    'item_type' => 'promotion_sample',
                                    'campaign_sample_id' => $sample->id,
                                    'gross' => 0,
                                    'commission' => $entry['commission_amount'],
                                    'net' => -$entry['commission_amount'],
                                    'description' => "عينة ترويج مع المسوقين — Promotion sample — {$productName} × {$sample->quantity}",
                                ]);
                            }

                            // Influencer platform fee (+VAT): one line per invitation, then the
                            // invitation is marked paid in the same transaction.
                            foreach ($feeLines as $line) {
                                $invitation = $line['invitation'];
                                $marketerName = $invitation->marketer?->name ?? '—';
                                $campaignTitle = $invitation->campaign?->title ?: ('#'.substr((string) $invitation->campaign_id, 0, 8));
                                $total = round($line['fee'] + $line['vat'] + $line['marketing'], 2);

                                PayoutItem::create([
                                    'payout_id' => $payout->id,
                                    'item_type' => 'influencer_fee',
                                    'invitation_id' => $invitation->id,
                                    'gross' => 0,
                                    'commission' => 0,
                                    'vat_amount' => $line['vat'],
                                    'marketing_fee_amount' => $line['marketing'],
                                    'net' => -$total,
                                    'description' => "رسوم مؤثر — Influencer fee — {$marketerName} — {$campaignTitle}"
                                        .($line['marketing'] > 0 ? ' — رسوم تسويق — Marketing fees '.number_format($line['marketing'], 2, '.', '') : ''),
                                ]);

                                $invitation->update([
                                    'platform_fee_status' => 'paid',
                                    'platform_fee_paid_via' => 'statement',
                                    'platform_fee_paid_at' => now(),
                                ]);
                                $paidInvitations[] = $invitation;
                            }

                            // Affiliate marketing fees accrued on affiliate campaigns: one line per settlement.
                            foreach ($affiliateLines as $settlement) {
                                $campaignTitle = $settlement->campaign?->title ?: ('#'.substr((string) $settlement->campaign_id, 0, 8));

                                PayoutItem::create([
                                    'payout_id' => $payout->id,
                                    'item_type' => 'affiliate_marketing_fee',
                                    'affiliate_fee_settlement_id' => $settlement->id,
                                    'gross' => 0,
                                    'commission' => 0,
                                    'marketing_fee_amount' => (float) $settlement->amount,
                                    'net' => -(float) $settlement->amount,
                                    'description' => "رسوم تسويق عمولة — Affiliate marketing fees — {$campaignTitle}",
                                ]);

                                $settlement->update([
                                    'status' => MarketerCampaignFeeSettlement::STATUS_PAID,
                                    'paid_via' => 'statement',
                                    'paid_at' => now(),
                                ]);
                            }

                            if (! empty($calc['ad_charge_ids'])) {
                                PaidAdCharge::whereIn('id', $calc['ad_charge_ids'])->update([
                                    'payout_id' => $payout->id,
                                    'settled_at' => now(),
                                ]);
                            }
                        });

                        // The influencer fee share is credited after the statement is committed; a failure is
                        // logged and retried by RetryMarketerFeeCreditsJob, never rolling the statement back.
                        foreach ($paidInvitations as $paidInvitation) {
                            app(MarketerFeePayoutService::class)->creditForInvitation($paidInvitation);
                        }

                        $generated++;
                    }
                }
            });

        Log::info("GenerateVendorPayoutsJob: generated {$generated} payout(s) for period {$this->periodStart->toDateString()} → {$this->periodEnd->toDateString()}.");

        if ($generated > 0) {
            Notification::send(
                Admin::permission('payouts.approve')->get(),
                new PayoutBatchReadyForApproval(
                    batchType: 'vendor',
                    payoutCount: $generated,
                    periodStart: $this->periodStart->toDateString(),
                    periodEnd: $this->periodEnd->toDateString(),
                ),
            );
        }
    }

    private function generatePayoutNumber(): string
    {
        $sequence = str_pad((string) (Payout::count() + 1), 5, '0', STR_PAD_LEFT);

        return 'PO-'.now()->format('Y').'-'.$sequence;
    }
}
