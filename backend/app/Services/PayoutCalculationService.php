<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Services\Ads\AdBillingService;
use Carbon\Carbon;

class PayoutCalculationService
{
    /**
     * Sub-order statuses that are eligible for payout calculation.
     */
    private const PAYABLE_STATUSES = ['delivered', 'completed'];

    public function __construct(private readonly AdBillingService $adBillingService)
    {
    }

    /**
     * Calculate payout summaries for a vendor over a given period, grouped by currency.
     *
     * sub_orders carry no currency column — currency is on the parent order.
     * Returns one entry per currency found in payable sub-orders for this vendor/period.
     *
     * BUSINESS DECISION (flag for finance team): when a vendor's sub-orders are in SAR
     * but their bank account is denominated in USD, we pay out in the SALES currency
     * (SAR) and let the payment rail handle any cross-currency conversion on their end,
     * where the exchange rate is stated and auditable. If a convert-at-payout approach
     * is preferred instead, a separate FX step with an explicit logged rate must be added
     * here — do NOT silently absorb or profit from FX spread.
     *
     * @return array<string, array{
     *   vendor_id: string,
     *   period_start: string,
     *   period_end: string,
     *   gross_sales: int,
     *   commission: int,
     *   gateway_fee_deducted: int,
     *   refunds_deducted: int,
     *   chargebacks_deducted: int,
     *   storage_fees: int,
     *   ad_fees: int,
     *   other_adjustments: int,
     *   net_amount: int,
     *   currency: string
     * }> Keyed by ISO currency code.
     */
    public function calculateForVendor(Vendor $vendor, Carbon $from, Carbon $to): array
    {
        // sub_orders has no currency column; join orders to get the order currency
        // so we can group and produce per-currency totals.
        $rows = SubOrder::where('sub_orders.vendor_id', $vendor->id)
            ->whereIn('sub_orders.status', self::PAYABLE_STATUSES)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('sub_orders.delivered_at', [$from->startOfDay(), $to->endOfDay()])
                  ->orWhereBetween('sub_orders.created_at', [$from->startOfDay(), $to->endOfDay()]);
            })
            ->where(function ($q) {
                // COD sub_orders are blocked until cash has been remitted by the delivery agent.
                // Non-COD sub_orders are always eligible regardless of the flag, since nothing
                // sets cod_remittance_confirmed=true for non-COD orders after creation.
                $q->whereHas('order', fn($q) => $q->where('payment_method', '!=', 'cod'))
                  ->orWhere('sub_orders.cod_remittance_confirmed', true);
            })
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->selectRaw('
                orders.currency                                        AS currency,
                COALESCE(SUM(sub_orders.subtotal), 0)            AS gross_sales,
                COALESCE(SUM(sub_orders.platform_commission), 0) AS commission,
                COALESCE(SUM(sub_orders.gateway_fee), 0)         AS gateway_fee_total,
                COALESCE(SUM(sub_orders.vendor_payout), 0)       AS vendor_payout_total
            ')
            ->groupBy('orders.currency')
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $currency    = $row->currency;
            $grossSales  = (int) $row->gross_sales;
            $commission  = (int) $row->commission;
            $gatewayFee  = (int) $row->gateway_fee_total;

            // Refunds where the vendor bears the cost — filter by matching currency.
            $refundsDeducted = (int) Refund::whereHas('subOrder', fn($q) => $q->where('vendor_id', $vendor->id))
                ->where('vendor_charged_back', true)
                ->where('status', RefundStatus::Completed->value)
                ->where('currency', $currency)
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount');

            // Chargebacks — payment_transactions.currency exists; filter by it.
            $chargebacksDeducted = (int) PaymentTransaction::where('type', 'chargeback')
                ->whereHas('order', fn($q) => $q->whereHas('subOrders', fn($s) => $s->where('vendor_id', $vendor->id)))
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->where('currency', $currency)
                ->whereBetween('processed_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount');

            $netBeforeAds = $grossSales - $commission - $gatewayFee - $refundsDeducted - $chargebacksDeducted;

            [$adFees, $adChargeIds] = $this->selectAdCharges($vendor, $currency, $netBeforeAds);

            $netAmount = $netBeforeAds - $adFees;

            $results[$currency] = [
                'vendor_id'                  => $vendor->id,
                'period_start'               => $from->toDateString(),
                'period_end'                 => $to->toDateString(),
                'gross_sales'                => $grossSales,
                'commission'                 => $commission,
                'gateway_fee_deducted'       => $gatewayFee,
                'refunds_deducted'           => $refundsDeducted,
                'chargebacks_deducted'       => $chargebacksDeducted,
                'storage_fees'               => 0,
                'ad_fees'                    => $adFees,
                'other_adjustments'          => 0,
                'net_amount'                 => max(0, $netAmount),
                'currency'                   => $currency,
                'ad_charge_ids'              => $adChargeIds,
            ];
        }

        return $results;
    }

    /**
     * Deterministically select which unsettled PaidAdCharge rows go into this
     * payout: all negative (refund) rows first, then positive rows oldest-first,
     * stopping at the first positive row that would push the running sum above
     * max(0, $netBeforeAds). Rows not taken stay unsettled and roll forward.
     *
     * @return array{0: int, 1: array<int, string>} [ad_fees, charge_ids]
     */
    private function selectAdCharges(Vendor $vendor, string $currency, int $netBeforeAds): array
    {
        $charges = $this->adBillingService->unsettledForPayout($vendor, $currency)
            ->sortBy('created_at')
            ->values();

        $cap = max(0, $netBeforeAds);

        $refunds = $charges->filter(fn ($c) => ($c->amount + $c->tax_amount) < 0);
        $positives = $charges->filter(fn ($c) => ($c->amount + $c->tax_amount) >= 0);

        $sum = 0;
        $ids = [];

        foreach ($refunds as $charge) {
            $sum += $charge->amount + $charge->tax_amount;
            $ids[] = $charge->id;
        }

        foreach ($positives as $charge) {
            $amount = $charge->amount + $charge->tax_amount;
            if ($sum + $amount > $cap) {
                break;
            }
            $sum += $amount;
            $ids[] = $charge->id;
        }

        return [$sum, $ids];
    }
}
