<?php

namespace App\Services\Ads;

use App\Enums\PaidAdChargeType;
use App\Enums\PaidAdPaymentStatus;
use App\Enums\PaidAdSlotPricingModel;
use App\Models\Admin;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCharge;
use App\Models\PaidAdDailyStat;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdBillingService
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    /**
     * Collects payment/reservation for a newly approved booking, according to its payment_method.
     */
    public function collect(PaidAdBooking $b): void
    {
        DB::transaction(function () use ($b) {
            $isFixed = PaidAdSlotPricingModel::from($b->pricing_model)->isFixed();

            if ($b->payment_method->value === 'wallet') {
                $wallet = $this->walletService->getOrCreateWallet($this->ownerType($b), $this->ownerId($b), $b->currency);

                if ($isFixed) {
                    $amount = $b->quoted_amount + $b->tax_amount;
                    $tx = $this->walletService->debit(
                        $wallet, $amount, 'paid_ad_booking', $b->id,
                        "Ad booking {$b->booking_reference} payment", null
                    );

                    PaidAdCharge::create([
                        'paid_ad_booking_id' => $b->id,
                        'advertiser_type' => $b->advertiser_type->value,
                        'vendor_id' => $b->vendor_id,
                        'marketer_id' => $b->marketer_id,
                        'country_id' => $b->country_id,
                        'currency' => $b->currency,
                        'type' => PaidAdChargeType::Fixed->value,
                        'amount' => $amount,
                        'tax_amount' => $b->tax_amount,
                        'settlement' => 'wallet',
                        'wallet_transaction_id' => $tx->id,
                        'settled_at' => now(),
                    ]);

                    $b->update([
                        'payment_status' => PaidAdPaymentStatus::Paid->value,
                        'paid_at' => now(),
                        'total_charged' => $b->quoted_amount,
                    ]);
                } else {
                    $amount = $b->budget_amount;
                    $tx = $this->walletService->debit(
                        $wallet, $amount, 'paid_ad_booking', $b->id,
                        "Ad booking {$b->booking_reference} budget reserve", null
                    );

                    PaidAdCharge::create([
                        'paid_ad_booking_id' => $b->id,
                        'advertiser_type' => $b->advertiser_type->value,
                        'vendor_id' => $b->vendor_id,
                        'marketer_id' => $b->marketer_id,
                        'country_id' => $b->country_id,
                        'currency' => $b->currency,
                        'type' => PaidAdChargeType::BudgetReserve->value,
                        'amount' => $amount,
                        'settlement' => 'wallet',
                        'wallet_transaction_id' => $tx->id,
                        'settled_at' => now(),
                    ]);

                    $b->update(['payment_status' => PaidAdPaymentStatus::Paid->value, 'paid_at' => now()]);
                }

                return;
            }

            if ($b->payment_method->value === 'payout_deduction') {
                if ($isFixed) {
                    PaidAdCharge::create([
                        'paid_ad_booking_id' => $b->id,
                        'advertiser_type' => $b->advertiser_type->value,
                        'vendor_id' => $b->vendor_id,
                        'marketer_id' => $b->marketer_id,
                        'country_id' => $b->country_id,
                        'currency' => $b->currency,
                        'type' => PaidAdChargeType::Fixed->value,
                        'amount' => $b->quoted_amount + $b->tax_amount,
                        'tax_amount' => $b->tax_amount,
                        'settlement' => 'payout_deduction',
                    ]);
                    $b->update(['total_charged' => $b->quoted_amount]);
                }
                // cpm/cpc payout_deduction: no charge until delivery (recordDelivery()).
                $b->update(['payment_status' => PaidAdPaymentStatus::Reserved->value]);

                return;
            }

            // offline is handled by AdBookingService::markOfflinePaid(), not here.
        });
    }

    /**
     * Called by AS-05's delivery job when new impressions/clicks are recorded.
     */
    public function recordDelivery(PaidAdBooking $b, int $newImpressions, int $newClicks): void
    {
        DB::transaction(function () use ($b, $newImpressions, $newClicks) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();

            $pricingModel = PaidAdSlotPricingModel::from($b->pricing_model);

            $impressionsDelivered = $b->impressions_delivered + $newImpressions;
            $clicksDelivered = $b->clicks_delivered + $newClicks;

            $newBilledImpressions = $b->cpm_impressions_billed;
            $totalAmount = 0;
            $settlement = $b->payment_method->value === 'wallet' ? 'wallet' : 'payout_deduction';

            $makeCharge = function (PaidAdChargeType $type, int $amount) use ($b, $settlement) {
                PaidAdCharge::create([
                    'paid_ad_booking_id' => $b->id,
                    'advertiser_type' => $b->advertiser_type->value,
                    'vendor_id' => $b->vendor_id,
                    'marketer_id' => $b->marketer_id,
                    'country_id' => $b->country_id,
                    'currency' => $b->currency,
                    'type' => $type->value,
                    'amount' => $amount,
                    'settlement' => $settlement,
                    // wallet spend is drawn from the budget reserve already debited in collect();
                    // no new wallet transaction here.
                    'settled_at' => $settlement === 'wallet' ? now() : null,
                ]);
            };

            if ($pricingModel === PaidAdSlotPricingModel::Cpm) {
                // One charge per 1,000-impression block, so each block is independently auditable.
                $billableBlocks = intdiv($impressionsDelivered - $b->cpm_impressions_billed, 1000);
                for ($i = 0; $i < $billableBlocks; $i++) {
                    $remainingBudget = max(0, $b->budget_amount - $b->total_charged - $totalAmount);
                    if ($remainingBudget <= 0) {
                        break;
                    }
                    $amount = min($b->unit_rate, $remainingBudget);
                    $makeCharge(PaidAdChargeType::Cpm, $amount);
                    $totalAmount += $amount;
                    $newBilledImpressions += 1000;
                }
            } elseif ($pricingModel === PaidAdSlotPricingModel::Cpc && $newClicks > 0) {
                $remainingBudget = max(0, $b->budget_amount - $b->total_charged);
                $amount = min($newClicks * $b->unit_rate, $remainingBudget);
                if ($amount > 0) {
                    $makeCharge(PaidAdChargeType::Cpc, $amount);
                    $totalAmount += $amount;
                }
            }

            $b->update([
                'impressions_delivered' => $impressionsDelivered,
                'clicks_delivered' => $clicksDelivered,
                'cpm_impressions_billed' => $newBilledImpressions,
                'total_charged' => $b->total_charged + $totalAmount,
            ]);

            PaidAdDailyStat::updateOrCreate(
                ['paid_ad_booking_id' => $b->id, 'date' => now()->toDateString()],
                ['currency' => $b->currency]
            )->increment('impressions', $newImpressions);

            PaidAdDailyStat::where('paid_ad_booking_id', $b->id)
                ->where('date', now()->toDateString())
                ->update([
                    'clicks' => DB::raw('clicks + '.(int) $newClicks),
                    'spend' => DB::raw('spend + '.(int) $totalAmount),
                ]);

            if ($b->total_charged >= $b->budget_amount) {
                app(AdBookingService::class)->complete($b, 'budget_exhausted');
            }
        });
    }

    public function refund(PaidAdBooking $b, int $amount, string $note, ?Admin $admin = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($b, $amount, $note, $admin) {
            $lastCharge = PaidAdCharge::where('paid_ad_booking_id', $b->id)->latest('created_at')->first();
            $settlement = $lastCharge->settlement ?? 'wallet';

            if ($settlement === 'wallet') {
                $wallet = $this->walletService->getOrCreateWallet($this->ownerType($b), $this->ownerId($b), $b->currency);
                $tx = $this->walletService->credit(
                    $wallet, $amount, 'paid_ad_booking', $b->id, $note, $admin?->id
                );

                PaidAdCharge::create([
                    'paid_ad_booking_id' => $b->id,
                    'advertiser_type' => $b->advertiser_type->value,
                    'vendor_id' => $b->vendor_id,
                    'marketer_id' => $b->marketer_id,
                    'country_id' => $b->country_id,
                    'currency' => $b->currency,
                    'type' => 'refund',
                    'amount' => -$amount,
                    'settlement' => 'wallet',
                    'wallet_transaction_id' => $tx->id,
                    'settled_at' => now(),
                    'note' => $note,
                    'created_by_admin_id' => $admin?->id,
                ]);
            } else {
                $unsettled = PaidAdCharge::where('paid_ad_booking_id', $b->id)
                    ->where('settlement', 'payout_deduction')
                    ->whereNull('payout_id')
                    ->exists();

                if ($unsettled) {
                    PaidAdCharge::create([
                        'paid_ad_booking_id' => $b->id,
                        'advertiser_type' => $b->advertiser_type->value,
                        'vendor_id' => $b->vendor_id,
                        'marketer_id' => $b->marketer_id,
                        'country_id' => $b->country_id,
                        'currency' => $b->currency,
                        'type' => 'refund',
                        'amount' => -$amount,
                        'settlement' => 'payout_deduction',
                        'note' => $note,
                        'created_by_admin_id' => $admin?->id,
                    ]);
                } else {
                    $wallet = $this->walletService->getOrCreateWallet($this->ownerType($b), $this->ownerId($b), $b->currency);
                    $tx = $this->walletService->credit(
                        $wallet, $amount, 'paid_ad_booking', $b->id, $note, $admin?->id
                    );

                    PaidAdCharge::create([
                        'paid_ad_booking_id' => $b->id,
                        'advertiser_type' => $b->advertiser_type->value,
                        'vendor_id' => $b->vendor_id,
                        'marketer_id' => $b->marketer_id,
                        'country_id' => $b->country_id,
                        'currency' => $b->currency,
                        'type' => 'refund',
                        'amount' => -$amount,
                        'settlement' => 'wallet',
                        'wallet_transaction_id' => $tx->id,
                        'settled_at' => now(),
                        'note' => $note,
                        'created_by_admin_id' => $admin?->id,
                    ]);
                }
            }

            $totalCharged = PaidAdCharge::where('paid_ad_booking_id', $b->id)->sum('amount');
            $b->update([
                'payment_status' => $totalCharged > 0
                    ? PaidAdPaymentStatus::PartiallyRefunded->value
                    : PaidAdPaymentStatus::Refunded->value,
            ]);
        });
    }

    public function unsettledForPayout($vendor, string $currency): Collection
    {
        return PaidAdCharge::where('vendor_id', $vendor->id)
            ->where('currency', $currency)
            ->where('settlement', 'payout_deduction')
            ->whereNull('payout_id')
            ->get();
    }

    private function ownerType(PaidAdBooking $b): string
    {
        return $b->advertiser_type->value === 'marketer' ? 'marketer' : 'vendor';
    }

    private function ownerId(PaidAdBooking $b): string
    {
        return $b->advertiser_type->value === 'marketer' ? $b->marketer_id : $b->vendor_id;
    }
}
