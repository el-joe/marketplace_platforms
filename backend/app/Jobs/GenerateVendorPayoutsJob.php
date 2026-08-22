<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use App\Notifications\Admin\PayoutBatchReadyForApproval;
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
     * @param  \Carbon\Carbon  $periodStart  Start of the payout period (inclusive)
     * @param  \Carbon\Carbon  $periodEnd    End of the payout period (inclusive)
     */
    public function __construct(
        private readonly Carbon $periodStart,
        private readonly Carbon $periodEnd
    ) {
    }

    public function handle(PayoutCalculationService $calculationService): void
    {
        $generated = 0;

        Vendor::where('status', 'active')
            ->whereHas('bankAccounts', fn($q) => $q->where('is_primary', true)
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

                    if (!$primaryAccount) {
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

                        DB::transaction(function () use ($calc, $currency, $vendor, $primaryAccount) {
                            $payout = Payout::create([
                                'payout_number'        => $this->generatePayoutNumber(),
                                'vendor_id'            => $vendor->id,
                                'period_start'         => $calc['period_start'],
                                'period_end'           => $calc['period_end'],
                                'gross_sales'          => $calc['gross_sales'],
                                'commission'           => $calc['commission'],
                                'gateway_fee_deducted' => $calc['gateway_fee_deducted'],
                                'refunds_deducted'     => $calc['refunds_deducted'],
                                'chargebacks_deducted' => $calc['chargebacks_deducted'],
                                'storage_fees'         => $calc['storage_fees'],
                                'ad_fees'              => $calc['ad_fees'],
                                'other_adjustments'    => $calc['other_adjustments'],
                                'net_amount'           => $calc['net_amount'],
                                'currency'             => $currency, // sales currency — see PayoutCalculationService note on FX
                                'status'               => 'pending',
                                'bank_account_id'      => $primaryAccount->id,
                                'payout_method'        => 'bank_transfer',
                            ]);

                            foreach ($calc['promotion_fee_requests'] ?? [] as $request) {
                                $listing = $request->vendorListing ?? $request->adminListing;
                                $product = $listing?->productVariant?->product;
                                $productName = $product?->name_en ?? $product?->name_ar ?? '—';
                                $slots = $request->items->count();
                                $feePerSlot = $request->items->first()?->slot_promotion_fee ?? 0;

                                PayoutItem::create([
                                    'payout_id'             => $payout->id,
                                    'item_type'             => 'promotion_fee',
                                    'promotion_request_id'  => $request->id,
                                    'gross'                 => 0,
                                    'commission'            => 0,
                                    'net'                   => -$request->total_promotion_fee,
                                    'description'           => "Influencer Promotion Fee — {$productName} — {$slots} slots × {$feePerSlot}",
                                ]);

                                $request->update([
                                    'fee_deducted'    => true,
                                    'fee_deducted_at' => now(),
                                ]);
                            }

                            foreach ($calc['sample_items'] ?? [] as $entry) {
                                $sampleItem = $entry['item'];
                                $listing = $sampleItem->vendorListing;
                                $product = $listing?->productVariant?->product;
                                $productName = $product?->name_en ?? $product?->name_ar ?? '—';

                                // Price shown to vendor is ZERO (Promotion Sample) — only
                                // the admin fixed + variable commission is actually deducted.
                                PayoutItem::create([
                                    'payout_id'      => $payout->id,
                                    'item_type'      => 'promotion_sample',
                                    'sample_item_id' => $sampleItem->id,
                                    'gross'          => 0,
                                    'commission'     => $entry['commission_amount'],
                                    'net'            => -$entry['commission_amount'],
                                    'description'    => "ترويج عينة — Promotion Sample — {$productName}",
                                ]);

                                $sampleItem->update([
                                    'fee_deducted'    => true,
                                    'fee_deducted_at' => now(),
                                ]);
                            }
                        });

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
        return 'PO-' . now()->format('Y') . '-' . $sequence;
    }
}
