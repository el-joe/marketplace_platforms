<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\DeliveryAgentPayout;
use App\Models\MarketerPayout;
use App\Models\Order;
use App\Models\PaidAdBooking;
use App\Models\SubOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Currency-safe financial aggregations.
 *
 * Every method that touches monetary amounts groups results by country AND
 * currency so no cross-currency blending ever occurs.  The only place where
 * values from different currencies are combined is consolidatedRevenueUsd(),
 * which is explicitly labelled as a converted view and must be presented
 * as such in any UI that uses it.
 *
 * Exchange-rate direction: exchange_rate_to_base is the number of local-
 * currency units per 1 USD (e.g. AED = 3.673, SAR = 3.75).
 * To convert local → USD: divide by the rate.
 * To convert USD → local: multiply by the rate.
 */
class FinancialReportService
{
    // ── Revenue recognition policy ────────────────────────────────────────────
    //
    // ACCOUNTING DECISION (flag for finance team): only orders in a terminal
    // "money received and goods delivered" state are counted as recognised
    // revenue.  'placed'/'confirmed'/'shipped' are excluded because delivery
    // has not yet occurred and the order could still be cancelled or returned.
    // 'cancelled'/'refunded' are excluded because no net revenue was retained.
    // 'disputed' is excluded until the dispute resolves.
    //
    // If the finance team adopts a different recognition policy (e.g. accrual
    // at shipment rather than delivery), update REVENUE_STATUSES accordingly
    // and add a note explaining the policy change.
    private const REVENUE_STATUSES = ['delivered', 'completed'];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recognised order revenue grouped by country and currency.
     *
     * Returns one row per (country, currency) pair.  Rows where country_id is
     * NULL (orders placed before the backfill migration) are excluded — they
     * cannot be reliably attributed to a country.
     */
    public function revenueByCountry(Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->whereBetween('placed_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereNotNull('orders.country_id')
            ->join('countries', 'countries.id', '=', 'orders.country_id')
            ->groupBy('countries.id', 'countries.name_en', 'countries.currency_code')
            ->selectRaw('
                countries.id              AS country_id,
                countries.name_en         AS country_name,
                countries.currency_code   AS currency_code,
                SUM(orders.total)         AS total,
                SUM(orders.subtotal)      AS subtotal,
                SUM(orders.discount)      AS discount,
                SUM(orders.shipping)      AS shipping,
                SUM(orders.tax)           AS tax,
                COUNT(*)                  AS order_count
            ')
            ->get();
    }

    /**
     * Consolidated revenue expressed in USD using stored exchange rates.
     *
     * IMPORTANT: this is a converted view, not a transaction-accurate figure.
     * Exchange rates are point-in-time snapshots; actual FX realised at
     * payment processing may differ.  Always label this as
     * "Estimated USD equivalent (indicative)" in the UI.
     */
    public function consolidatedRevenueUsd(Carbon $from, Carbon $to): array
    {
        $byCountry = $this->revenueByCountry($from, $to);

        // exchange_rate_to_base = local units per 1 USD (e.g. AED 3.673 / USD).
        // Dividing local amount by the rate converts to USD.
        $rates = Currency::pluck('exchange_rate_to_base', 'code');

        $totalUsd = $byCountry->sum(function ($row) use ($rates) {
            $rate = $rates[$row->currency_code] ?? null;
            if (! $rate || $rate == 0) {
                return 0; // skip unknown currencies rather than dividing by zero
            }
            return $row->total / $rate;
        });

        return [
            'total_usd'  => round($totalUsd, 2),
            'label'      => 'Estimated USD equivalent (indicative — uses stored exchange rates, not realised FX)',
            'as_of'      => now()->toIso8601String(),
            'by_country' => $byCountry,
        ];
    }

    /**
     * Platform commission earned per country/currency (from sub_orders).
     *
     * sub_orders carry no currency column; currency is resolved via the parent
     * order.  Only payable statuses are included (same policy as vendor payouts).
     */
    public function commissionByCountry(Carbon $from, Carbon $to): Collection
    {
        return SubOrder::query()
            ->whereIn('sub_orders.status', ['delivered', 'completed'])
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->whereNotNull('orders.country_id')
            ->whereBetween('orders.placed_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->join('countries', 'countries.id', '=', 'orders.country_id')
            ->groupBy('countries.id', 'countries.name_en', 'countries.currency_code')
            ->selectRaw('
                countries.id                              AS country_id,
                countries.name_en                         AS country_name,
                countries.currency_code                   AS currency_code,
                SUM(sub_orders.platform_commission)       AS commission,
                COUNT(DISTINCT sub_orders.id)             AS sub_order_count
            ')
            ->get();
    }

    /**
     * Payment gateway fees deducted from vendors per country/currency (from sub_orders).
     *
     * This is a real money flow the platform should track for reconciliation against
     * what the payment gateway actually invoices — the fee is vendor-borne, never
     * platform revenue, but the platform remits it to the gateway on the vendor's behalf.
     */
    public function gatewayFeeByCountry(Carbon $from, Carbon $to): Collection
    {
        return SubOrder::query()
            ->whereIn('sub_orders.status', ['delivered', 'completed'])
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->whereNotNull('orders.country_id')
            ->whereBetween('orders.placed_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->join('countries', 'countries.id', '=', 'orders.country_id')
            ->groupBy('countries.id', 'countries.name_en', 'countries.currency_code')
            ->selectRaw('
                countries.id                              AS country_id,
                countries.name_en                         AS country_name,
                countries.currency_code                   AS currency_code,
                SUM(sub_orders.gateway_fee)               AS gateway_fee,
                COUNT(DISTINCT sub_orders.id)              AS sub_order_count
            ')
            ->get();
    }

    /**
     * Marketer payouts disbursed per country/currency.
     *
     * Uses the currency stored on each marketer_payout row (which is set from
     * the conversion currency at payout-generation time, not the marketer's
     * home country — a marketer can earn in multiple currencies).
     */
    public function marketerPayoutsByCountry(Carbon $from, Carbon $to): Collection
    {
        return MarketerPayout::query()
            ->whereBetween('marketer_payouts.created_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->join('marketers', 'marketers.id', '=', 'marketer_payouts.marketer_id')
            ->join('countries', 'countries.id', '=', 'marketers.country_id')
            ->groupBy('countries.id', 'countries.name_en', 'marketer_payouts.currency')
            ->selectRaw('
                countries.id                              AS country_id,
                countries.name_en                         AS country_name,
                marketer_payouts.currency                 AS currency_code,
                SUM(marketer_payouts.gross_commission) AS gross,
                SUM(marketer_payouts.tax_deduction)    AS tax_deducted,
                SUM(marketer_payouts.net_amount)       AS net,
                COUNT(*)                                   AS payout_count
            ')
            ->get();
    }

    /**
     * Paid-ad spend per country/currency (revenue to the platform from advertisers).
     *
     * PaidAdBooking has its own country_id and currency columns.
     * Only 'active'/'completed' bookings represent collected revenue.
     */
    public function adSpendByCountry(Carbon $from, Carbon $to): Collection
    {
        return PaidAdBooking::query()
            ->whereIn('paid_ad_bookings.status', ['active', 'completed'])
            ->whereBetween('paid_ad_bookings.created_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull('paid_ad_bookings.country_id')
            ->join('countries', 'countries.id', '=', 'paid_ad_bookings.country_id')
            ->groupBy('countries.id', 'countries.name_en', 'paid_ad_bookings.currency')
            ->selectRaw('
                countries.id                          AS country_id,
                countries.name_en                     AS country_name,
                paid_ad_bookings.currency             AS currency_code,
                SUM(paid_ad_bookings.total_charged) AS spend,
                COUNT(*)                              AS booking_count
            ')
            ->get();
    }

    /**
     * VAT collected per country, cross-checked against expected amounts.
     *
     * Returns both the VAT stored on the order (orders.tax) and the amount
     * that would be expected given the country's current vat_rate applied to
     * the taxable base (subtotal + shipping).
     *
     * Discrepancies between collected_vat and expected_vat indicate
     * either historical miscalculation or a vat_rate change since the order was
     * placed.  These rows should be reviewed by the finance team.
     *
     * NOTE: "taxable base = subtotal + shipping" was the formula used at checkout
     * time prior to 2026-07-05. CheckoutController now computes tax via
     * CheckoutCalculationService, which taxes (subtotal - discount) at the order
     * level and plain per-item subtotal at the sub-order/line level — shipping is
     * no longer part of the taxable base. Orders placed after that date will show
     * as discrepancies here until this formula (or the checkout one) is reconciled
     * by whoever owns tax policy.
     */
    public function vatCollectedByCountry(Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->whereBetween('placed_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereNotNull('orders.country_id')
            ->join('countries', 'countries.id', '=', 'orders.country_id')
            ->groupBy('countries.id', 'countries.name_en', 'countries.currency_code', 'countries.vat_rate')
            ->selectRaw('
                countries.id                            AS country_id,
                countries.name_en                       AS country_name,
                countries.currency_code                 AS currency_code,
                countries.vat_rate                      AS vat_rate_pct,
                SUM(orders.tax)                         AS collected_vat,
                ROUND(
                    SUM((orders.subtotal - orders.discount + orders.shipping) * (countries.vat_rate / 100))
                )                                       AS expected_vat,
                COUNT(*)                                AS order_count
            ')
            ->get()
            ->each(function ($row) {
                // Flag rows where collected VAT deviates from expected by more
                // than 1 cent (rounding) per order.
                $tolerance = $row->order_count;
                $row->has_vat_discrepancy = abs($row->collected_vat - $row->expected_vat) > $tolerance;
            });
    }

    /**
     * Exceptional-zone shipping gap subsidies, grouped by currency.
     *
     * sub_orders carry no currency column; currency is resolved via the parent
     * order's country, same as commissionByCountry()/gatewayFeeByCountry().
     * NEVER sum across currencies — each row here is a distinct currency total.
     */
    public function exceptionalZoneSubsidySummaryByCurrency(Carbon $from, Carbon $to): Collection
    {
        return SubOrder::query()
            ->where('sub_orders.shipping_gap', '>', 0)
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->whereNotNull('orders.country_id')
            ->whereBetween('sub_orders.created_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->join('countries', 'countries.id', '=', 'orders.country_id')
            ->groupBy('countries.currency_code')
            ->selectRaw('
                countries.currency_code                     AS currency_code,
                SUM(sub_orders.shipping_gap)                AS total_gap,
                SUM(sub_orders.admin_subsidy_amount)         AS admin_absorbed,
                SUM(sub_orders.vendor_contribution_amount)   AS vendor_contributed,
                COUNT(*)                                     AS exceptional_orders
            ')
            ->get();
    }

    /**
     * Top zones by admin-absorbed cost for exceptional-zone deliveries in the period.
     *
     * Joins sub_orders.exceptional_zone_subsidy_id → platform_shipping_subsidies →
     * shipping_zones to attribute the admin cost to the zone that generated it.
     */
    public function exceptionalZoneSubsidyTopZones(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        return SubOrder::query()
            ->where('sub_orders.shipping_gap', '>', 0)
            ->whereNotNull('sub_orders.exceptional_zone_subsidy_id')
            ->whereBetween('sub_orders.created_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->join('platform_shipping_subsidies', 'platform_shipping_subsidies.id', '=', 'sub_orders.exceptional_zone_subsidy_id')
            ->join('shipping_zones', 'shipping_zones.id', '=', 'platform_shipping_subsidies.shipping_zone_id')
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->join('countries', 'countries.id', '=', 'orders.country_id')
            ->groupBy('shipping_zones.id', 'shipping_zones.name', 'countries.currency_code')
            ->selectRaw('
                shipping_zones.id                            AS zone_id,
                shipping_zones.name                          AS zone_name,
                countries.currency_code                      AS currency_code,
                SUM(sub_orders.admin_subsidy_amount)         AS admin_absorbed,
                SUM(sub_orders.vendor_contribution_amount)   AS vendor_contributed,
                SUM(sub_orders.shipping_gap)                 AS total_gap,
                COUNT(*)                                      AS exceptional_orders
            ')
            ->orderByDesc('admin_absorbed')
            ->limit($limit)
            ->get();
    }
}
