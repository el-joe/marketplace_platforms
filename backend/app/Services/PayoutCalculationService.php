<?php

namespace App\Services;

use App\Enums\FbnStorageFeeStatus;
use App\Enums\PackagingSupplyRequestStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Enums\VendorSubscriptionInvoiceStatus;
use App\Models\FbnDailyOverageFee;
use App\Models\FbnStorageFee;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\PackagingSupplyRequest;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorSubscriptionInvoice;
use App\Services\Ads\AdBillingService;
use App\Services\Campaign\CampaignSampleService;
use Carbon\Carbon;

class PayoutCalculationService
{
    /**
     * Sub-order statuses that are eligible for payout calculation.
     */
    private const PAYABLE_STATUSES = ['delivered', 'completed'];

    public function __construct(private readonly AdBillingService $adBillingService) {}

    /**
     * Calculate payout summaries for a vendor over a given period, grouped by currency.
     *
     * sub_orders carry no currency column — currency is on the parent order.
     * Returns one entry per currency found in payable sub-orders for this vendor/period.
     *
     * enhancement.md P-11: eligibility for a sub-order to be included in ANY
     * payout, ever, is:
     *   - status = 'completed', OR status = 'delivered' AND every one of its
     *     order_items' return window has passed (return_eligible_until <
     *     today, or null which means "no returnable items on this sub-order");
     *   - if the order is COD, cod_remittance_confirmed = true;
     *   - the sub-order does NOT already appear in a payout_items row
     *     (`whereDoesntHave('payoutItems')`) — this is the fix for the
     *     double-pay bug: consecutive payout runs used to select by a
     *     delivered_at/created_at date window with no "already paid" guard,
     *     so a sub-order delivered near a period boundary, or simply left
     *     unpaid in a prior run, would be paid again.
     *
     * The $from/$to window is used only to label the resulting Payout's
     * period_start/period_end — it is NOT used to gate which sub-orders are
     * eligible, precisely so a vendor's backlog of not-yet-paid sub-orders
     * from an earlier period is picked up by the next run instead of being
     * silently skipped forever.
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
     *   currency: string,
     *   sub_order_ids: array<int, string>,
     *   ad_charge_ids: array<int, string>,
     *   packaging_request_ids: array<int, string>,
     *   subscription_invoice_ids: array<int, string>,
     * }> Keyed by ISO currency code.
     */
    public function calculateForVendor(Vendor $vendor, Carbon $from, Carbon $to): array
    {
        // sub_orders has no currency column; join orders to get the order currency
        // so we can group and produce per-currency totals.
        $eligible = SubOrder::where('sub_orders.vendor_id', $vendor->id)
            ->whereIn('sub_orders.status', self::PAYABLE_STATUSES)
            ->where(function ($q) {
                $q->where('sub_orders.status', 'completed')
                    ->orWhere(function ($q2) {
                        // 'delivered': eligible once every item's return window
                        // has passed (or the sub-order has no returnable items).
                        $q2->where('sub_orders.status', 'delivered')
                            ->whereDoesntHave('items', function ($itemQuery) {
                                $itemQuery->whereNotNull('return_eligible_until')
                                    ->where('return_eligible_until', '>=', now()->toDateString());
                            });
                    });
            })
            ->where(function ($q) {
                // COD sub_orders are blocked until cash has been remitted by the delivery agent.
                // Non-COD sub_orders are always eligible regardless of the flag, since nothing
                // sets cod_remittance_confirmed=true for non-COD orders after creation.
                $q->whereHas('order', fn ($q) => $q->where('payment_method', '!=', 'cod'))
                    ->orWhere('sub_orders.cod_remittance_confirmed', true);
            })
            // Core double-pay fix: never re-select a sub-order that already
            // has a payout_items row from a prior payout run.
            ->whereDoesntHave('payoutItems')
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->get(['sub_orders.*', 'orders.currency as order_currency']);

        $byCurrency = $eligible->groupBy('order_currency');

        $results = [];

        foreach ($byCurrency as $currency => $subOrders) {
            $grossSales = round((float) $subOrders->sum('subtotal'), 2);
            $commission = round((float) $subOrders->sum('platform_commission'), 2);
            $gatewayFee = round((float) $subOrders->sum('gateway_fee'), 2);
            // vendor_payout is CheckoutPricingEngine's already-net figure per
            // sub-order: gross - vendor_coupon_cost - platform_commission -
            // gateway_fee - vendor_contribution_amount - vendor-owned
            // marketer_commission. Summing it directly (rather than
            // recomputing gross - commission - gateway_fee) is what makes
            // this payout actually account for the coupon share, the
            // delivery-fee gap the vendor covers, and vendor-campaign
            // marketer commission — all of which the old implementation
            // silently ignored.
            $vendorPayoutTotal = round((float) $subOrders->sum('vendor_payout'), 2);
            $subOrderIds = $subOrders->pluck('id')->all();

            // Refunds where the vendor bears the cost — filter by matching currency.
            $refundsDeducted = round((float) Refund::whereIn('sub_order_id', $subOrderIds)
                ->where('vendor_charged_back', true)
                ->where('status', RefundStatus::Completed->value)
                ->where('currency', $currency)
                ->sum('amount'), 2);

            // Chargebacks — payment_transactions.currency exists; filter by it.
            $chargebacksDeducted = round((float) PaymentTransaction::where('type', 'chargeback')
                ->whereHas('order', fn ($q) => $q->whereHas('subOrders', fn ($s) => $s->whereIn('id', $subOrderIds)))
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->where('currency', $currency)
                ->whereBetween('processed_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount'), 2);

            // FBN storage + daily overage fees not yet settled, for this vendor/currency.
            $storageFees = FbnStorageFee::where('vendor_id', $vendor->id)
                ->where('status', FbnStorageFeeStatus::Pending)
                ->where('currency', $currency)
                ->get();
            $overageFees = FbnDailyOverageFee::where('vendor_id', $vendor->id)
                ->where('status', FbnStorageFeeStatus::Pending)
                ->where('currency', $currency)
                ->get();
            $storageFeesTotal = round((float) $storageFees->sum('total_fee') + (float) $overageFees->sum('total_fee'), 2);

            // Packaging supply requests delivered but not yet deducted from a payout.
            $packagingRequests = PackagingSupplyRequest::where('vendor_id', $vendor->id)
                ->where('status', PackagingSupplyRequestStatus::Delivered)
                ->whereNull('fee_deducted_at')
                ->where('currency', $currency)
                ->get();
            $packagingTotal = round((float) $packagingRequests->sum(fn ($r) => $r->total_cost + $r->delivery_fee), 2);

            // Open vendor subscription invoices for this currency.
            $subscriptionInvoices = VendorSubscriptionInvoice::where('vendor_id', $vendor->id)
                ->where('status', VendorSubscriptionInvoiceStatus::Open)
                ->where('currency', $currency)
                ->get();
            $subscriptionTotal = round((float) $subscriptionInvoices->sum('amount'), 2);

            $netBeforeSamples = round($vendorPayoutTotal - $refundsDeducted - $chargebacksDeducted - $storageFeesTotal - $packagingTotal - $subscriptionTotal, 2);

            // Promotion samples dispatched from stock: 0-priced lines that only
            // carry the admin sale commission (see CampaignSampleService).
            [$sampleCommission, $sampleItems] = $this->selectSampleCommissions($vendor, $currency, $netBeforeSamples);

            // Campaign fees the seller's wallet did not cover when the campaign went live: deducted here, once.
            [$campaignFees, $campaignFeeItems] = $this->selectCampaignFees($vendor, $currency, $netBeforeSamples - $sampleCommission);

            // Commissions paid out of the campaigns' Max Commission Budget since the last statement: affiliate / broker
            // conversions released to the marketer wallet, and influencer fixed amounts paid at campaign completion.
            [$budgetCommissions, $budgetCommissionItems] = $this->selectBudgetCommissions($vendor, $currency, $netBeforeSamples - $sampleCommission - $campaignFees);

            $netBeforeAds = round($netBeforeSamples - $sampleCommission - $campaignFees - $budgetCommissions, 2);

            [$adFees, $adChargeIds] = $this->selectAdCharges($vendor, $currency, $netBeforeAds);

            $netAmount = round($netBeforeAds - $adFees, 2);

            $results[$currency] = [
                'vendor_id' => $vendor->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'gross_sales' => $grossSales,
                'commission' => round($commission + $sampleCommission, 2),
                'gateway_fee_deducted' => $gatewayFee,
                'refunds_deducted' => $refundsDeducted,
                'chargebacks_deducted' => $chargebacksDeducted,
                'storage_fees' => $storageFeesTotal,
                'ad_fees' => $adFees,
                'other_adjustments' => -round($packagingTotal + $subscriptionTotal + $campaignFees + $budgetCommissions, 2),
                'campaign_fees' => $campaignFees,
                'campaign_fee_items' => $campaignFeeItems,
                'budget_commissions' => $budgetCommissions,
                'budget_commission_items' => $budgetCommissionItems,
                'net_amount' => max(0, $netAmount),
                'currency' => $currency,
                'sub_order_ids' => $subOrderIds,
                'ad_charge_ids' => $adChargeIds,
                'sample_items' => $sampleItems,
                'storage_fee_ids' => $storageFees->pluck('id')->all(),
                'overage_fee_ids' => $overageFees->pluck('id')->all(),
                'packaging_request_ids' => $packagingRequests->pluck('id')->all(),
                'subscription_invoice_ids' => $subscriptionInvoices->pluck('id')->all(),
            ];
        }

        return $results;
    }

    /**
     * Promotion-sample commissions to deduct on this statement: every dispatched
     * sample of the vendor not yet on a statement (payout_items.campaign_sample_id
     * is unique, so a sample can never be charged twice), oldest first, as far as
     * the statement can absorb. What does not fit rolls forward to the next run.
     *
     * @return array{0: float, 1: array<int, array{item: MarketerCampaignSample, commission_amount: float}>}
     */
    private function selectSampleCommissions(Vendor $vendor, string $currency, float $netBefore): array
    {
        $cap = max(0.0, $netBefore);
        $sum = 0.0;
        $items = [];

        foreach (app(CampaignSampleService::class)->unsettledFor($vendor, $currency) as $sample) {
            $amount = (float) $sample->commission_amount;
            if (round($sum + $amount, 2) > $cap) {
                break;
            }
            $sum = round($sum + $amount, 2);
            $items[] = ['item' => $sample, 'commission_amount' => $amount];
        }

        return [$sum, $items];
    }

    /**
     * Campaign fees (fixed + variable) still `pending` at statement time: the campaign went live but the vendor
     * wallet could not cover the fee. payout_items.campaign_id is unique, so a fee is never charged twice. Oldest
     * first, as far as the statement can absorb; the rest rolls forward. The job re-checks each row under a lock.
     *
     * @return array{0: float, 1: array<int, array{item: MarketerCampaign, fee: float}>}
     */
    private function selectCampaignFees(Vendor $vendor, string $currency, float $netBefore): array
    {
        $cap = max(0.0, $netBefore);
        $sum = 0.0;
        $items = [];

        $campaigns = MarketerCampaign::query()
            ->where('vendor_id', $vendor->id)
            ->where('currency', $currency)
            ->where('campaign_fee_status', 'pending')
            ->where('marketing_fee_total', '>', 0)
            ->whereIn('status', ['active', 'auto_approved', 'paused', 'done'])
            ->whereDoesntHave('payoutItems')
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get();

        foreach ($campaigns as $campaign) {
            $fee = round((float) $campaign->marketing_fee_total, 2);
            if (round($sum + $fee, 2) > $cap) {
                break;
            }
            $sum = round($sum + $fee, 2);
            $items[] = ['item' => $campaign, 'fee' => $fee];
        }

        return [$sum, $items];
    }

    /**
     * Commissions funded by the vendor's campaign budgets, not yet on any statement (payout_items.marketer_conversion_id
     * / invitation_id are unique):
     *
     *   - conversions released to the marketer wallet. Only v3 rows (marketer_type set) are taken: older conversions
     *     were already deducted from the vendor payout at checkout. The amount is the GROSS commission (the marketer's
     *     net plus the platform's commission fee);
     *   - influencer fixed amounts paid at campaign completion (gross as well).
     *
     * Oldest first, as far as the statement can absorb; the rest rolls forward.
     *
     * @return array{0: float, 1: array<int, array{type: string, item: MarketerCampaignConversion|MarketerCampaignInvitation, amount: float}>}
     */
    private function selectBudgetCommissions(Vendor $vendor, string $currency, float $netBefore): array
    {
        $cap = max(0.0, $netBefore);
        $sum = 0.0;
        $items = [];

        $candidates = collect();

        MarketerCampaignConversion::query()
            ->whereNotNull('marketer_type')
            ->where('status', 'approved')
            ->whereNotNull('wallet_released_at')
            ->where('currency', $currency)
            ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->whereDoesntHave('payoutItems')
            ->with('campaign:id,title')
            ->orderBy('wallet_released_at')
            ->orderBy('id')
            ->get()
            ->each(fn (MarketerCampaignConversion $c) => $candidates->push([
                'at' => $c->wallet_released_at, 'type' => 'conversion', 'item' => $c,
                'amount' => round((float) ($c->gross_commission_amount ?? $c->commission_amount), 2),
            ]));

        MarketerCampaignInvitation::query()
            ->where('influencer_fixed_status', MarketerCampaignInvitation::FIXED_PAID)
            ->where('influencer_fixed_amount', '>', 0)
            ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendor->id)->where('currency', $currency))
            ->whereDoesntHave('payoutItems')
            ->with(['campaign:id,title', 'marketer:id,name'])
            ->orderBy('influencer_fixed_paid_at')
            ->orderBy('id')
            ->get()
            ->each(fn (MarketerCampaignInvitation $i) => $candidates->push([
                'at' => $i->influencer_fixed_paid_at, 'type' => 'influencer_fixed', 'item' => $i,
                'amount' => round((float) $i->influencer_fixed_amount, 2),
            ]));

        foreach ($candidates->sortBy('at')->values() as $entry) {
            if ($entry['amount'] <= 0) {
                continue;
            }
            if (round($sum + $entry['amount'], 2) > $cap) {
                break;
            }
            $sum = round($sum + $entry['amount'], 2);
            $items[] = ['type' => $entry['type'], 'item' => $entry['item'], 'amount' => $entry['amount']];
        }

        return [$sum, $items];
    }

    /**
     * Deterministically select which unsettled PaidAdCharge rows go into this
     * payout: all negative (refund) rows first, then positive rows oldest-first,
     * stopping at the first positive row that would push the running sum above
     * max(0, $netBeforeAds). Rows not taken stay unsettled and roll forward.
     *
     * @return array{0: float, 1: array<int, string>} [ad_fees, charge_ids]
     */
    private function selectAdCharges(Vendor $vendor, string $currency, float $netBeforeAds): array
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
