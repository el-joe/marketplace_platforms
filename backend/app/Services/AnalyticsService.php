<?php

namespace App\Services;

use App\Models\FlashSaleAnalytic;
use App\Traits\HasCurrencyAwareAggregates;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    use HasCurrencyAwareAggregates;

    // ── Period resolution ────────────────────────────────────────────────────

    /**
     * Returns ['from', 'to', 'prev_from', 'prev_to'] as Carbon instances.
     */
    private function dateRange(Request $request): array
    {
        $period = $request->input('period', 'month');
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                $from = $now->copy()->startOfDay();
                $to = $now->copy()->endOfDay();
                break;
            case 'week':
                $from = $now->copy()->subDays(6)->startOfDay();
                $to = $now->copy()->endOfDay();
                break;
            case 'quarter':
                $from = $now->copy()->subDays(89)->startOfDay();
                $to = $now->copy()->endOfDay();
                break;
            case 'year':
                $from = $now->copy()->subDays(364)->startOfDay();
                $to = $now->copy()->endOfDay();
                break;
            case 'custom':
                $from = Carbon::parse($request->input('date_from', $now->copy()->subDays(29)->toDateString()))->startOfDay();
                $to = Carbon::parse($request->input('date_to', $now->toDateString()))->endOfDay();
                break;
            default: // month
                $from = $now->copy()->subDays(29)->startOfDay();
                $to = $now->copy()->endOfDay();
        }

        $diff = $from->diffInSeconds($to);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($diff);

        return compact('from', 'to', 'prevFrom', 'prevTo');
    }

    private function cacheKey(string $method, Request $request): string
    {
        $period = $request->input('period', 'month');
        $countryId = $request->input('country_id', 'all');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        return "analytics.{$method}.{$period}.{$countryId}.{$dateFrom}.{$dateTo}";
    }

    private function cacheTtl(string $period): int
    {
        return ($period === 'today') ? 300 : 3600;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function changePct($current, $previous): array
    {
        $current = (float) $current;
        $previous = (float) $previous;
        $pct = $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : null;

        return [
            'value' => $current,
            'previous' => $previous,
            'change_pct' => $pct,
            'direction' => $pct === null ? 'flat' : ($pct >= 0 ? 'up' : 'down'),
        ];
    }

    /**
     * Build currency-grouped changePct for a multi-currency collection.
     *
     * @param array $curRows  rows from DB::select, each with ->currency and ->$valueKey
     * @param array $prevRows same shape, prior period
     * @param string $valueKey column name (already in display units, not cents)
     */
    private function changePctByCurrency(array $curRows, array $prevRows, string $valueKey): array
    {
        $cur  = collect($curRows)->keyBy('currency');
        $prev = collect($prevRows)->keyBy('currency');
        $currencies = $cur->keys()->merge($prev->keys())->unique();

        return $currencies->map(function ($c) use ($cur, $prev, $valueKey) {
            return array_merge(
                ['currency' => $c],
                $this->changePct($cur[$c]->{$valueKey} ?? 0, $prev[$c]->{$valueKey} ?? 0)
            );
        })->values()->all();
    }

    // ── Overview ─────────────────────────────────────────────────────────────

    public function overview(Request $request): array
    {
        $key = $this->cacheKey('overview', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');

            // Always JOIN customers so we can group by country; filter when countryId is set.
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = fn($from, $to) => array_values(array_filter(
                [$from->toDateTimeString(), $to->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            // FIX lines 111-112, 126-127: GROUP BY o.currency — never blend across currencies.
            $sql = "
                SELECT
                    o.currency,
                    COALESCE(SUM(o.total), 0)                            AS gmv,
                    COALESCE(SUM(so.commission), 0)                      AS commission,
                    COUNT(DISTINCT o.id)                                  AS orders_count
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                LEFT JOIN (
                    SELECT order_id, SUM(platform_commission) AS commission
                    FROM sub_orders GROUP BY order_id
                ) so ON so.order_id = o.id
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY o.currency
            ";

            $cur  = DB::select($sql, $bindings($r['from'], $r['to']));
            $prev = DB::select($sql, $bindings($r['prevFrom'], $r['prevTo']));

            $curByCur  = collect($cur)->keyBy('currency');
            $prevByCur = collect($prev)->keyBy('currency');
            $currencies = $curByCur->keys()->merge($prevByCur->keys())->unique();

            // Build per-currency totals for consolidatedUsd (trait method).
            $curGmvColl  = collect($cur)->map(fn($r) => (object)['currency' => $r->currency, 'total' => $r->gmv]);
            $prevGmvColl = collect($prev)->map(fn($r) => (object)['currency' => $r->currency, 'total' => $r->gmv]);
            $curCommColl  = collect($cur)->map(fn($r) => (object)['currency' => $r->currency, 'total' => $r->commission]);
            $prevCommColl = collect($prev)->map(fn($r) => (object)['currency' => $r->currency, 'total' => $r->commission]);

            $curGmvUsd   = $this->consolidatedUsd($curGmvColl);
            $prevGmvUsd  = $this->consolidatedUsd($prevGmvColl);
            $curCommUsd  = $this->consolidatedUsd($curCommColl);
            $prevCommUsd = $this->consolidatedUsd($prevCommColl);

            $curOrders  = (int) collect($cur)->sum('orders_count');
            $prevOrders = (int) collect($prev)->sum('orders_count');
            $curAov     = $curOrders > 0 ? $curGmvUsd / $curOrders : 0;
            $prevAov    = $prevOrders > 0 ? $prevGmvUsd / $prevOrders : 0;

            if ($currencies->count() === 1) {
                // Single-currency path: return native amounts.
                $c = $currencies->first();
                $curGmvNative  = ($curByCur[$c]->gmv ?? 0) / 100;
                $prevGmvNative = ($prevByCur[$c]->gmv ?? 0) / 100;
                $curCommNative  = ($curByCur[$c]->commission ?? 0) / 100;
                $prevCommNative = ($prevByCur[$c]->commission ?? 0) / 100;
                $curOrdersSingle  = (int) ($curByCur[$c]->orders_count ?? 0);
                $prevOrdersSingle = (int) ($prevByCur[$c]->orders_count ?? 0);
                $curAovNative  = $curOrdersSingle > 0 ? $curGmvNative / $curOrdersSingle : 0;
                $prevAovNative = $prevOrdersSingle > 0 ? $prevGmvNative / $prevOrdersSingle : 0;

                $gmvShape = array_merge($this->changePct($curGmvNative, $prevGmvNative), ['currency' => $c]);
                $revShape = array_merge($this->changePct($curCommNative, $prevCommNative), ['currency' => $c]);
                $aovShape = array_merge($this->changePct($curAovNative, $prevAovNative), ['currency' => $c]);
            } else {
                // Multi-currency: expose per-currency breakdown + USD equivalent via consolidatedUsd().
                $gmvByCurrency = $this->changePctByCurrency(
                    $curByCur->map(fn($r) => (object)['currency' => $r->currency, 'value' => $r->gmv / 100])->values()->all(),
                    $prevByCur->map(fn($r) => (object)['currency' => $r->currency, 'value' => $r->gmv / 100])->values()->all(),
                    'value'
                );
                $commByCurrency = $this->changePctByCurrency(
                    $curByCur->map(fn($r) => (object)['currency' => $r->currency, 'value' => $r->commission / 100])->values()->all(),
                    $prevByCur->map(fn($r) => (object)['currency' => $r->currency, 'value' => $r->commission / 100])->values()->all(),
                    'value'
                );

                $gmvShape = array_merge(
                    $this->changePct($curGmvUsd, $prevGmvUsd),
                    ['currency' => 'USD', 'is_usd_equivalent' => true, 'by_currency' => $gmvByCurrency]
                );
                $revShape = array_merge(
                    $this->changePct($curCommUsd, $prevCommUsd),
                    ['currency' => 'USD', 'is_usd_equivalent' => true, 'by_currency' => $commByCurrency]
                );
                $aovShape = array_merge(
                    $this->changePct($curAov, $prevAov),
                    ['currency' => 'USD', 'is_usd_equivalent' => true]
                );
            }

            // New customers
            $newCur = DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM customers WHERE created_at >= ? AND created_at <= ?',
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString()]
            );
            $newPrev = DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM customers WHERE created_at >= ? AND created_at <= ?',
                [$r['prevFrom']->toDateTimeString(), $r['prevTo']->toDateTimeString()]
            );

            // Active vendors (had sub-orders in period)
            $avCur = DB::selectOne("
                SELECT COUNT(DISTINCT so.vendor_id) AS cnt
                FROM sub_orders so
                JOIN orders o ON o.id = so.order_id
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
            ", [$r['from']->toDateTimeString(), $r['to']->toDateTimeString()]);
            $avPrev = DB::selectOne("
                SELECT COUNT(DISTINCT so.vendor_id) AS cnt
                FROM sub_orders so
                JOIN orders o ON o.id = so.order_id
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
            ", [$r['prevFrom']->toDateTimeString(), $r['prevTo']->toDateTimeString()]);

            // SLA breach rate
            $slaCur = DB::selectOne("
                SELECT
                    COALESCE(SUM(CASE WHEN so.sla_breached = 1 THEN 1 ELSE 0 END), 0) AS breached,
                    COUNT(so.id) AS total
                FROM sub_orders so
                JOIN orders o ON o.id = so.order_id
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
            ", [$r['from']->toDateTimeString(), $r['to']->toDateTimeString()]);
            $slaPrev = DB::selectOne("
                SELECT
                    COALESCE(SUM(CASE WHEN so.sla_breached = 1 THEN 1 ELSE 0 END), 0) AS breached,
                    COUNT(so.id) AS total
                FROM sub_orders so
                JOIN orders o ON o.id = so.order_id
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
            ", [$r['prevFrom']->toDateTimeString(), $r['prevTo']->toDateTimeString()]);

            $slaCompCur = $slaCur->total > 0 ? round((1 - $slaCur->breached / $slaCur->total) * 100, 2) : 100.0;
            $slaCompPrev = $slaPrev->total > 0 ? round((1 - $slaPrev->breached / $slaPrev->total) * 100, 2) : 100.0;

            // Return rate
            $retCur = DB::selectOne("
                SELECT COUNT(*) AS cnt FROM return_requests
                WHERE created_at >= ? AND created_at <= ?
            ", [$r['from']->toDateTimeString(), $r['to']->toDateTimeString()]);
            $retPrev = DB::selectOne("
                SELECT COUNT(*) AS cnt FROM return_requests
                WHERE created_at >= ? AND created_at <= ?
            ", [$r['prevFrom']->toDateTimeString(), $r['prevTo']->toDateTimeString()]);

            $retRateCur = $curOrders > 0 ? round(($retCur->cnt / $curOrders) * 100, 2) : 0.0;
            $retRatePrev = $prevOrders > 0 ? round(($retPrev->cnt / $prevOrders) * 100, 2) : 0.0;

            return [
                'gmv' => $gmvShape,
                'revenue' => $revShape,
                'orders_count' => $this->changePct($curOrders, $prevOrders),
                'avg_order_value' => $aovShape,
                'new_customers' => $this->changePct($newCur->cnt, $newPrev->cnt),
                'active_vendors' => $this->changePct($avCur->cnt, $avPrev->cnt),
                'sla_compliance' => $this->changePct($slaCompCur, $slaCompPrev),
                'return_rate' => $this->changePct($retRateCur, $retRatePrev),
            ];
        });
    }

    // ── Revenue chart ─────────────────────────────────────────────────────────

    public function revenueChart(Request $request): array
    {
        $key = $this->cacheKey('revenue-chart', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');

            // FIX lines 230-233: GROUP BY (date, o.currency) — never blend across currencies.
            // Always join customers to resolve currency via country. Filter when countryId is set.
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [
                    $r['from']->toDateTimeString(),
                    $r['to']->toDateTimeString(),
                    $countryId ?: null,
                ],
                fn($v) => $v !== null
            ));

            $rows = DB::select("
                SELECT
                    DATE(o.placed_at)                           AS date,
                    o.currency,
                    COALESCE(SUM(o.total), 0)                   AS gmv,
                    COALESCE(SUM(so.commission), 0)             AS commission,
                    COALESCE(SUM(so.payout), 0)                 AS vendor_payouts,
                    COALESCE(SUM(rf.refunded), 0)               AS refunds
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                LEFT JOIN (
                    SELECT order_id,
                           SUM(platform_commission) AS commission,
                           SUM(vendor_payout)       AS payout
                    FROM sub_orders GROUP BY order_id
                ) so ON so.order_id = o.id
                LEFT JOIN (
                    SELECT order_id, SUM(amount) AS refunded
                    FROM refunds WHERE status = 'completed'
                    GROUP BY order_id
                ) rf ON rf.order_id = o.id
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY DATE(o.placed_at), o.currency
                ORDER BY date ASC, o.currency ASC
            ", $bindings);

            // Group rows by currency, each currency gets its own time-series dataset.
            $byCurrency = collect($rows)->groupBy('currency');
            $currencies = $byCurrency->keys()->sort()->values()->all();

            if (\count($currencies) === 1) {
                // Single currency (country filter applied): return flat arrays, same shape as before.
                $currency = $currencies[0];
                $labels = $gmv = $commission = $payouts = $refunds = [];
                foreach ($byCurrency[$currency] as $row) {
                    $labels[]     = $row->date;
                    $gmv[]        = (float) ($row->gmv / 100);
                    $commission[] = (float) ($row->commission / 100);
                    $payouts[]    = (float) ($row->vendor_payouts / 100);
                    $refunds[]    = (float) ($row->refunds / 100);
                }
                return compact('labels', 'gmv', 'commission', 'payouts', 'refunds', 'currency');
            }

            // Multi-currency: return one dataset per currency so the chart renders separate lines.
            $allDates = collect($rows)->pluck('date')->unique()->sort()->values()->all();
            $datasets = [];
            foreach ($currencies as $currency) {
                $byDate = collect($byCurrency[$currency])->keyBy('date');
                $gmv = $commission = $payouts = $refunds = [];
                foreach ($allDates as $date) {
                    $row          = $byDate[$date] ?? null;
                    $gmv[]        = $row ? (float) ($row->gmv / 100) : 0;
                    $commission[] = $row ? (float) ($row->commission / 100) : 0;
                    $payouts[]    = $row ? (float) ($row->vendor_payouts / 100) : 0;
                    $refunds[]    = $row ? (float) ($row->refunds / 100) : 0;
                }
                $datasets[$currency] = compact('gmv', 'commission', 'payouts', 'refunds');
            }

            return ['labels' => $allDates, 'currencies' => $currencies, 'datasets_by_currency' => $datasets];
        });
    }

    // ── Orders by status ──────────────────────────────────────────────────────

    public function ordersByStatus(Request $request): array
    {
        $key = $this->cacheKey('orders-by-status', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            $rows = DB::select("
                SELECT o.status, COUNT(*) AS cnt
                FROM orders o
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY o.status
                ORDER BY cnt DESC
            ", $bindings);

            $colorMap = [
                'placed' => '#6366f1',
                'confirmed' => '#8b5cf6',
                'partially_shipped' => '#f59e0b',
                'shipped' => '#3b82f6',
                'partially_delivered' => '#06b6d4',
                'delivered' => '#10b981',
                'completed' => '#22c55e',
                'cancelled' => '#ef4444',
                'refunded' => '#f97316',
                'disputed' => '#dc2626',
            ];

            $labels = $counts = $colors = [];
            foreach ($rows as $row) {
                $labels[] = ucfirst(str_replace('_', ' ', $row->status));
                $counts[] = (int) $row->cnt;
                $colors[] = $colorMap[$row->status] ?? '#94a3b8';
            }

            return compact('labels', 'counts', 'colors');
        });
    }

    // ── Orders by payment method ──────────────────────────────────────────────

    public function ordersByPaymentMethod(Request $request): array
    {
        $key = $this->cacheKey('orders-by-payment', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            // FIX extra SUM(total) by payment method: group by (payment_method, currency).
            $rows = DB::select("
                SELECT
                    o.payment_method,
                    o.currency,
                    COUNT(*) AS orders_count,
                    COALESCE(SUM(o.total), 0) AS total_amount
                FROM orders o
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY o.payment_method, o.currency
                ORDER BY total_amount DESC
            ", $bindings);

            // Collapse by payment_method, keeping per-currency revenue breakdown.
            $byMethod = [];
            foreach ($rows as $row) {
                $m = $row->payment_method;
                if (!isset($byMethod[$m])) {
                    $byMethod[$m] = ['payment_method' => $m, 'orders_count' => 0, 'amount_by_currency' => []];
                }
                $byMethod[$m]['orders_count'] += (int) $row->orders_count;
                $byMethod[$m]['amount_by_currency'][] = [
                    'currency' => $row->currency,
                    'amount'   => (float) ($row->total_amount / 100),
                ];
            }

            $labels = $counts = $amounts = [];
            foreach ($byMethod as $m => $data) {
                $labels[]  = strtoupper($m);
                $counts[]  = $data['orders_count'];
                // For the bar chart axis, sum via consolidatedUsd so lengths are comparable.
                $coll = collect($data['amount_by_currency'])
                    ->map(fn($c) => (object)['currency' => $c['currency'], 'total' => $c['amount'] * 100]);
                $amounts[] = round($this->consolidatedUsd($coll), 2);
            }

            return [
                'labels'  => $labels,
                'counts'  => $counts,
                'amounts' => $amounts,           // USD-equivalent for chart axis
                'rows'    => array_values($byMethod), // full per-currency detail
            ];
        });
    }

    // ── Top products ─────────────────────────────────────────────────────────

    public function topProducts(Request $request): array
    {
        $key = $this->cacheKey('top-products', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            // FIX lines 355-356: GROUP BY (product, o.currency) — never blend revenue across currencies.
            $rows = DB::select("
                SELECT
                    p.id,
                    p.name_en                           AS name,
                    MAX(COALESCE(vl.vendor_sku, oi.sku)) AS sku,
                    o.currency,
                    SUM(oi.quantity)                    AS units_sold,
                    COALESCE(SUM(oi.line_total), 0)     AS revenue
                FROM order_items oi
                JOIN product_variants pv ON pv.id = oi.product_variant_id
                JOIN products p          ON p.id  = pv.product_id
                LEFT JOIN (
                    SELECT
                        product_variant_id,
                        vendor_id,
                        MAX(vendor_sku) AS vendor_sku
                    FROM vendor_listings
                    GROUP BY product_variant_id, vendor_id
                ) vl ON vl.product_variant_id = oi.product_variant_id AND vl.vendor_id = oi.vendor_id
                JOIN orders o            ON o.id  = oi.order_id
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY p.id, p.name_en, o.currency
                ORDER BY p.id ASC
            ", $bindings);

            // Collapse per-product, accumulate revenue_by_currency.
            $products = [];
            foreach ($rows as $row) {
                $id = $row->id;
                if (!isset($products[$id])) {
                    $products[$id] = [
                        'id'                 => $row->id,
                        'name'               => $row->name,
                        'sku'                => $row->sku,
                        'units_sold'         => 0,
                        'revenue_by_currency' => [],
                    ];
                }
                $products[$id]['units_sold'] += (int) $row->units_sold;
                $products[$id]['revenue_by_currency'][] = [
                    'currency' => $row->currency,
                    'revenue'  => round($row->revenue / 100, 2),
                ];
            }

            // Rank by units_sold (currency-agnostic).
            usort($products, fn($a, $b) => $b['units_sold'] - $a['units_sold']);

            return \array_slice(array_values($products), 0, 20);
        });
    }

    // ── Top vendors ──────────────────────────────────────────────────────────

    public function topVendors(Request $request): array
    {
        $key = $this->cacheKey('top-vendors', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            // FIX lines 401-402: GROUP BY (vendor, o.currency) — vendors on a multi-country
            // platform could theoretically have orders in different currencies; the raw SUM
            // would blend them. Group by currency and rank by USD equivalent via consolidatedUsd().
            $rows = DB::select("
                SELECT
                    v.id,
                    v.store_name                                  AS store_name,
                    v.store_rating_avg                            AS rating,
                    o.currency,
                    COUNT(DISTINCT so.id)                         AS orders_count,
                    COALESCE(SUM(so.subtotal), 0)                 AS gmv,
                    COALESCE(SUM(so.platform_commission), 0)      AS commission
                FROM sub_orders so
                JOIN vendors v  ON v.id  = so.vendor_id
                JOIN orders o   ON o.id  = so.order_id
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY v.id, v.store_name, v.store_rating_avg, o.currency
                ORDER BY v.id ASC
            ", $bindings);

            // Collapse per-vendor, accumulate per-currency GMV/commission.
            $vendors = [];
            foreach ($rows as $row) {
                $id = $row->id;
                if (!isset($vendors[$id])) {
                    $vendors[$id] = [
                        'id'                    => $row->id,
                        'store_name'            => $row->store_name,
                        'rating'                => (float) $row->rating,
                        'orders_count'          => 0,
                        'gmv_by_currency'       => [],
                        'commission_by_currency' => [],
                    ];
                }
                $vendors[$id]['orders_count'] += (int) $row->orders_count;
                $vendors[$id]['gmv_by_currency'][] = [
                    'currency' => $row->currency,
                    'gmv'      => round($row->gmv / 100, 2),
                ];
                $vendors[$id]['commission_by_currency'][] = [
                    'currency'   => $row->currency,
                    'commission' => round($row->commission / 100, 2),
                ];
            }

            // Rank by USD-equivalent GMV using consolidatedUsd() from the trait.
            foreach ($vendors as &$vendor) {
                $coll = collect($vendor['gmv_by_currency'])
                    ->map(fn($c) => (object)['currency' => $c['currency'], 'total' => $c['gmv'] * 100]);
                $vendor['gmv_usd_equivalent'] = $this->consolidatedUsd($coll);
            }
            unset($vendor);

            usort($vendors, fn($a, $b) => $b['gmv_usd_equivalent'] <=> $a['gmv_usd_equivalent']);

            return \array_slice(array_values($vendors), 0, 20);
        });
    }

    // ── Top categories ────────────────────────────────────────────────────────

    public function topCategories(Request $request): array
    {
        $key = $this->cacheKey('top-categories', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            // FIX lines 437-438: GROUP BY (category, o.currency) — mirrors the topProducts fix.
            // NOTE: this is a SEPARATE code path from topProducts (categories vs products);
            // they are not duplicates — topProducts ranks individual SKUs, topCategories
            // rolls up to category level for the donut chart.
            $rows = DB::select("
                SELECT
                    cat.id,
                    cat.name_en                             AS name,
                    o.currency,
                    SUM(oi.quantity)                        AS units_sold,
                    COALESCE(SUM(oi.line_total), 0)         AS revenue
                FROM order_items oi
                JOIN product_variants pv ON pv.id = oi.product_variant_id
                JOIN products p          ON p.id  = pv.product_id
                JOIN categories cat      ON cat.id = p.category_id
                JOIN orders o            ON o.id  = oi.order_id
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY cat.id, cat.name_en, o.currency
                ORDER BY cat.id ASC
            ", $bindings);

            // Collapse per-category, accumulate revenue_by_currency; rank by USD equivalent.
            $categories = [];
            foreach ($rows as $row) {
                $id = $row->id;
                if (!isset($categories[$id])) {
                    $categories[$id] = [
                        'id'                  => $row->id,
                        'name'                => $row->name,
                        'revenue_by_currency' => [],
                    ];
                }
                $categories[$id]['revenue_by_currency'][] = [
                    'currency' => $row->currency,
                    'revenue'  => round($row->revenue / 100, 2),
                ];
            }

            foreach ($categories as &$cat) {
                $coll = collect($cat['revenue_by_currency'])
                    ->map(fn($c) => (object)['currency' => $c['currency'], 'total' => $c['revenue'] * 100]);
                $cat['revenue_usd_equivalent'] = $this->consolidatedUsd($coll);
            }
            unset($cat);

            usort($categories, fn($a, $b) => $b['revenue_usd_equivalent'] <=> $a['revenue_usd_equivalent']);
            $top = \array_slice(array_values($categories), 0, 15);

            $labels = $revenues = [];
            foreach ($top as $cat) {
                $labels[]   = $cat['name'];
                $revenues[] = round($cat['revenue_usd_equivalent'], 2);
            }

            return compact('labels', 'revenues');
        });
    }

    // ── Customer stats ────────────────────────────────────────────────────────

    public function customerStats(Request $request): array
    {
        $key = $this->cacheKey('customer-stats', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryWhere = $countryId ? ' AND country_id = ?' : '';
            $bindings = fn($from, $to) => array_values(array_filter(
                [$from->toDateTimeString(), $to->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            // Daily new customers chart
            $acqRows = DB::select("
                SELECT DATE(created_at) AS date, COUNT(*) AS cnt
                FROM customers
                WHERE created_at >= ? AND created_at <= ?
                {$countryWhere}
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ", $bindings($r['from'], $r['to']));

            // Returning vs new (placed orders in period)
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $ordersCountryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $rvn = DB::selectOne("
                SELECT
                    SUM(CASE WHEN o.customer_order_rank = 1 THEN 1 ELSE 0 END) AS new_buyers,
                    SUM(CASE WHEN o.customer_order_rank > 1 THEN 1 ELSE 0 END) AS returning_buyers
                FROM (
                    SELECT o.customer_id,
                           ROW_NUMBER() OVER (PARTITION BY o.customer_id ORDER BY o.placed_at ASC) AS customer_order_rank
                    FROM orders o
                    {$countryJoin}
                    WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                    {$ordersCountryWhere}
                ) o
            ", $bindings($r['from'], $r['to']));

            // Top countries by new customers
            $topCountries = DB::select("
                SELECT
                    co.name_en AS country,
                    COUNT(c.id) AS cnt
                FROM customers c
                JOIN countries co ON co.id = c.country_id
                WHERE c.created_at >= ? AND c.created_at <= ?
                {$countryWhere}
                GROUP BY co.name_en
                ORDER BY cnt DESC
                LIMIT 10
            ", $bindings($r['from'], $r['to']));

            return [
                'acquisition_chart' => [
                    'labels' => array_column($acqRows, 'date'),
                    'counts' => array_map(fn($r) => (int) $r->cnt, $acqRows),
                ],
                'returning_vs_new' => [
                    'new_buyers' => (int) ($rvn->new_buyers ?? 0),
                    'returning_buyers' => (int) ($rvn->returning_buyers ?? 0),
                ],
                'top_countries' => array_map(fn($row) => [
                    'country' => $row->country,
                    'count' => (int) $row->cnt,
                ], $topCountries),
            ];
        });
    }

    // ── Search analytics ──────────────────────────────────────────────────────

    public function searchAnalytics(Request $request): array
    {
        $key = $this->cacheKey('search-analytics', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $bindings = [$r['from']->toDateTimeString(), $r['to']->toDateTimeString()];

            $topQueries = DB::select("
                SELECT query_normalized AS query, COUNT(*) AS searches,
                       SUM(CASE WHEN clicked_product_id IS NOT NULL THEN 1 ELSE 0 END) AS clicks,
                       ROUND(SUM(CASE WHEN clicked_product_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS ctr
                FROM search_logs
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY query_normalized
                ORDER BY searches DESC
                LIMIT 20
            ", $bindings);

            $zeroResults = DB::select("
                SELECT query_normalized AS query, COUNT(*) AS searches
                FROM search_logs
                WHERE created_at >= ? AND created_at <= ? AND results_count = 0
                GROUP BY query_normalized
                ORDER BY searches DESC
                LIMIT 20
            ", $bindings);

            $volumeRows = DB::select("
                SELECT DATE(created_at) AS date, COUNT(*) AS cnt
                FROM search_logs
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ", $bindings);

            $totals = DB::selectOne("
                SELECT
                    COUNT(*) AS total_searches,
                    ROUND(SUM(CASE WHEN clicked_product_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS avg_ctr,
                    ROUND(SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS zero_result_rate
                FROM search_logs
                WHERE created_at >= ? AND created_at <= ?
            ", $bindings);

            return [
                'total_searches' => (int) $totals->total_searches,
                'avg_ctr' => (float) ($totals->avg_ctr ?? 0),
                'zero_result_rate' => (float) ($totals->zero_result_rate ?? 0),
                'top_queries' => array_map(fn($r) => [
                    'query' => $r->query,
                    'searches' => (int) $r->searches,
                    'clicks' => (int) $r->clicks,
                    'ctr' => (float) $r->ctr,
                ], $topQueries),
                'zero_result_queries' => array_map(fn($r) => [
                    'query' => $r->query,
                    'searches' => (int) $r->searches,
                ], $zeroResults),
                'volume_chart' => [
                    'labels' => array_column($volumeRows, 'date'),
                    'counts' => array_map(fn($r) => (int) $r->cnt, $volumeRows),
                ],
            ];
        });
    }

    // ── Product analytics ─────────────────────────────────────────────────────

    public function productAnalytics(Request $request): array
    {
        $key = $this->cacheKey('product-analytics', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $bindings = [$r['from']->toDateTimeString(), $r['to']->toDateTimeString()];

            $totals = DB::selectOne("
                SELECT COUNT(*) AS total_views
                FROM product_views
                WHERE created_at >= ? AND created_at <= ?
            ", $bindings);

            $topViewed = DB::select("
                SELECT p.id, p.name_en AS name, COUNT(pv.id) AS views
                FROM product_views pv
                JOIN products p ON p.id = pv.product_id
                WHERE pv.created_at >= ? AND pv.created_at <= ?
                GROUP BY p.id, p.name_en
                ORDER BY views DESC
                LIMIT 20
            ", $bindings);

            $viewSources = DB::select("
                SELECT source, COUNT(*) AS cnt
                FROM product_views
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY source
                ORDER BY cnt DESC
            ", $bindings);

            // Conversion funnel: views → add_to_carts → orders
            $funnel = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM product_views
                     WHERE created_at >= ? AND created_at <= ?) AS views,
                    (SELECT COUNT(*) FROM cart_items ci
                     JOIN carts ca ON ca.id = ci.cart_id
                     WHERE ca.created_at >= ? AND ca.created_at <= ?) AS cart_adds,
                    (SELECT COUNT(*) FROM order_items oi
                     JOIN orders o ON o.id = oi.order_id
                     WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL) AS purchases
            ", [
                $r['from']->toDateTimeString(),
                $r['to']->toDateTimeString(),
                $r['from']->toDateTimeString(),
                $r['to']->toDateTimeString(),
                $r['from']->toDateTimeString(),
                $r['to']->toDateTimeString(),
            ]);

            return [
                'total_views' => (int) $totals->total_views,
                'top_viewed' => array_map(fn($row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'views' => (int) $row->views,
                ], $topViewed),
                'view_sources' => array_map(fn($row) => [
                    'source' => $row->source,
                    'count' => (int) $row->cnt,
                ], $viewSources),
                'conversion_funnel' => [
                    'views' => (int) $funnel->views,
                    'cart_adds' => (int) $funnel->cart_adds,
                    'purchases' => (int) $funnel->purchases,
                ],
            ];
        });
    }

    // ── SLA metrics ───────────────────────────────────────────────────────────

    public function slaMetrics(Request $request): array
    {
        $key = $this->cacheKey('sla-metrics', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            $overall = DB::selectOne("
                SELECT
                    COUNT(so.id) AS total_sub_orders,
                    SUM(CASE WHEN so.sla_breached = 1 THEN 1 ELSE 0 END) AS breached,
                    ROUND(AVG(CASE WHEN so.shipped_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, o.placed_at, so.shipped_at)
                    END), 2) AS avg_ship_hours,
                    ROUND(AVG(CASE WHEN so.delivered_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, so.shipped_at, so.delivered_at)
                    END), 2) AS avg_delivery_hours
                FROM sub_orders so
                JOIN orders o ON o.id = so.order_id
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
            ", $bindings);

            $byVendor = DB::select("
                SELECT
                    v.store_name,
                    COUNT(so.id) AS total,
                    SUM(CASE WHEN so.sla_breached = 1 THEN 1 ELSE 0 END) AS breached,
                    ROUND(SUM(CASE WHEN so.sla_breached = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(so.id), 0), 2) AS breach_rate
                FROM sub_orders so
                JOIN vendors v  ON v.id  = so.vendor_id
                JOIN orders o   ON o.id  = so.order_id
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY v.id, v.store_name
                HAVING total > 0
                ORDER BY breach_rate DESC
                LIMIT 20
            ", $bindings);

            $trend = DB::select("
                SELECT
                    DATE(o.placed_at) AS date,
                    ROUND(SUM(CASE WHEN so.sla_breached = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(so.id), 0), 2) AS breach_rate
                FROM sub_orders so
                JOIN orders o ON o.id = so.order_id
                {$countryJoin}
                WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                {$countryWhere}
                GROUP BY DATE(o.placed_at)
                ORDER BY date ASC
            ", $bindings);

            $total = (int) $overall->total_sub_orders;
            $breached = (int) $overall->breached;
            $breachRate = $total > 0 ? round($breached / $total * 100, 2) : 0;

            return [
                'breach_rate' => $breachRate,
                'sla_compliance_pct' => round(100 - $breachRate, 2),
                'avg_ship_hours' => (float) ($overall->avg_ship_hours ?? 0),
                'avg_delivery_hours' => (float) ($overall->avg_delivery_hours ?? 0),
                'breach_by_vendor' => array_map(fn($row) => [
                    'store_name' => $row->store_name,
                    'total' => (int) $row->total,
                    'breached' => (int) $row->breached,
                    'breach_rate' => (float) $row->breach_rate,
                ], $byVendor),
                'trend' => [
                    'labels' => array_column($trend, 'date'),
                    'breach_rates' => array_map(fn($r) => (float) $r->breach_rate, $trend),
                ],
            ];
        });
    }

    // ── Ad performance ────────────────────────────────────────────────────────

    public function adPerformance(Request $request): array
    {
        $key = $this->cacheKey('ad-performance', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');

            // FIX lines 765-768, 776-781, 793-796:
            // ad_daily_stats has country_id. Make the join to countries MANDATORY so every
            // row carries its currency. Never SUM spend/revenue across countries (= currencies).
            $dateWhere  = 'ads.date >= ? AND ads.date <= ?';
            $bindings   = [$r['from']->toDateString(), $r['to']->toDateString()];
            if ($countryId) {
                $countryFilter = ' AND ads.country_id = ?';
                $bindings[]    = $countryId;
            } else {
                $countryFilter = '';
            }

            // Totals: GROUP BY country so spend/revenue are never blended across currencies.
            $totalRows = DB::select("
                SELECT
                    ads.country_id,
                    co.currency_code                                  AS currency,
                    co.name_en                                        AS country_name,
                    COALESCE(SUM(ads.impressions), 0)                 AS impressions,
                    COALESCE(SUM(ads.clicks), 0)                      AS clicks,
                    COALESCE(SUM(ads.conversions), 0)                 AS conversions,
                    COALESCE(SUM(ads.spend), 0)                 AS spend,
                    COALESCE(SUM(ads.revenue_attributed), 0)    AS revenue
                FROM ad_daily_stats ads
                JOIN countries co ON co.id = ads.country_id
                WHERE {$dateWhere}{$countryFilter}
                GROUP BY ads.country_id, co.currency_code, co.name_en
                ORDER BY spend DESC
            ", $bindings);

            // Aggregate CTR/ACOS only after grouping.
            $totalImpressions = array_sum(array_column($totalRows, 'impressions'));
            $totalClicks      = array_sum(array_column($totalRows, 'clicks'));
            $totalConversions = array_sum(array_column($totalRows, 'conversions'));
            $avgCtr = $totalImpressions > 0
                ? round($totalClicks * 100.0 / $totalImpressions, 4)
                : 0;

            // Spend and revenue are per-currency; use consolidatedUsd() for a single display number.
            $spendColl   = collect($totalRows)->map(fn($r) => (object)['currency' => $r->currency, 'total' => $r->spend]);
            $revenueColl = collect($totalRows)->map(fn($r) => (object)['currency' => $r->currency, 'total' => $r->revenue]);
            $totalSpendUsd   = $this->consolidatedUsd($spendColl);
            $totalRevenueUsd = $this->consolidatedUsd($revenueColl);
            $avgAcos = $totalRevenueUsd > 0
                ? round($totalSpendUsd * 100.0 / $totalRevenueUsd, 4)
                : 0;

            $spendByCountry = array_map(fn($r) => [
                'country_id'   => $r->country_id,
                'country_name' => $r->country_name,
                'currency'     => $r->currency,
                'spend'        => round($r->spend / 100, 2),
                'revenue'      => round($r->revenue / 100, 2),
            ], $totalRows);

            // Top campaigns: GROUP BY (campaign, country) so spend is per-currency.
            $topCampaigns = DB::select("
                SELECT
                    ac.name                                          AS campaign_name,
                    co.currency_code                                 AS currency,
                    SUM(ads.impressions)                            AS impressions,
                    SUM(ads.clicks)                                 AS clicks,
                    COALESCE(SUM(ads.spend), 0)               AS spend,
                    COALESCE(SUM(ads.revenue_attributed), 0)  AS revenue,
                    ROUND(SUM(ads.clicks) * 100.0 / NULLIF(SUM(ads.impressions), 0), 4) AS ctr,
                    ROUND(SUM(ads.spend) * 100.0 / NULLIF(SUM(ads.revenue_attributed), 0), 4) AS acos
                FROM ad_daily_stats ads
                JOIN ad_campaigns ac ON ac.id = ads.ad_campaign_id
                JOIN countries co    ON co.id = ads.country_id
                WHERE {$dateWhere}{$countryFilter}
                GROUP BY ac.id, ac.name, co.currency_code
                ORDER BY spend DESC
                LIMIT 20
            ", $bindings);

            // Performance chart: GROUP BY (date, country) — one data-point per (date, currency).
            $perfRows = DB::select("
                SELECT
                    ads.date,
                    co.currency_code                                 AS currency,
                    SUM(ads.impressions)                            AS impressions,
                    SUM(ads.clicks)                                 AS clicks,
                    COALESCE(SUM(ads.spend), 0)               AS spend,
                    COALESCE(SUM(ads.revenue_attributed), 0)  AS revenue
                FROM ad_daily_stats ads
                JOIN countries co ON co.id = ads.country_id
                WHERE {$dateWhere}{$countryFilter}
                GROUP BY ads.date, co.currency_code
                ORDER BY ads.date ASC, co.currency_code ASC
            ", $bindings);

            // Group perf chart by currency.
            $perfByCurrency = collect($perfRows)->groupBy('currency');
            $perfCurrencies = $perfByCurrency->keys()->sort()->values()->all();
            $allPerfDates   = collect($perfRows)->pluck('date')->unique()->sort()->values()->all();

            if (\count($perfCurrencies) === 1) {
                $currency = $perfCurrencies[0];
                $byDate   = collect($perfByCurrency[$currency])->keyBy('date');
                $perfChart = [
                    'labels'      => $allPerfDates,
                    'currency'    => $currency,
                    'impressions' => array_map(fn($d) => (int) ($byDate[$d]->impressions ?? 0), $allPerfDates),
                    'clicks'      => array_map(fn($d) => (int) ($byDate[$d]->clicks ?? 0), $allPerfDates),
                    'spend'       => array_map(fn($d) => round(($byDate[$d]->spend ?? 0) / 100, 2), $allPerfDates),
                    'revenue'     => array_map(fn($d) => round(($byDate[$d]->revenue ?? 0) / 100, 2), $allPerfDates),
                ];
            } else {
                $datasets = [];
                foreach ($perfCurrencies as $currency) {
                    $byDate = collect($perfByCurrency[$currency])->keyBy('date');
                    $datasets[$currency] = [
                        'spend'   => array_map(fn($d) => round(($byDate[$d]->spend ?? 0) / 100, 2), $allPerfDates),
                        'revenue' => array_map(fn($d) => round(($byDate[$d]->revenue ?? 0) / 100, 2), $allPerfDates),
                    ];
                }
                $perfChart = [
                    'labels'               => $allPerfDates,
                    'currencies'           => $perfCurrencies,
                    'datasets_by_currency' => $datasets,
                ];
            }

            return [
                'total_impressions'  => $totalImpressions,
                'total_clicks'       => $totalClicks,
                'total_conversions'  => $totalConversions,
                'total_spend'        => round($totalSpendUsd, 2),
                'total_revenue'      => round($totalRevenueUsd, 2),
                'spend_by_country'   => $spendByCountry,
                'avg_ctr'            => (float) $avgCtr,
                'avg_acos'           => (float) $avgAcos,
                'top_campaigns'      => array_map(fn($row) => [
                    'name'        => $row->campaign_name,
                    'currency'    => $row->currency,
                    'impressions' => (int) $row->impressions,
                    'clicks'      => (int) $row->clicks,
                    'spend'       => round($row->spend / 100, 2),
                    'revenue'     => round($row->revenue / 100, 2),
                    'ctr'         => (float) $row->ctr,
                    'acos'        => (float) $row->acos,
                ], $topCampaigns),
                'performance_chart' => $perfChart,
            ];
        });
    }

    // ── Flash sale analytics ──────────────────────────────────────────────────

    public function flashSaleAnalytics(Request $request): array
    {
        $key = $this->cacheKey('flash-sales', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');

            $scopeCountry = function ($query) use ($countryId) {
                if ($countryId) {
                    $query->whereHas('vendor', fn($q) => $q->where('country_id', $countryId));
                }
                return $query;
            };

            // FIX lines 844-845: flash_sale_analytics already has a currency column written
            // correctly by FlashSaleAnalyticsJob. The read-side just needs GROUP BY currency.
            // Use sumByCurrency() from the trait for the per-currency revenue breakdown.
            $revenueByMoneyCol = $this->sumByCurrency(
                $scopeCountry(FlashSaleAnalytic::query()
                    ->where('date', '>=', $r['from']->toDateString())
                    ->where('date', '<=', $r['to']->toDateString())),
                'gross_revenue'
            );
            $discountByMoneyCol = $this->sumByCurrency(
                $scopeCountry(FlashSaleAnalytic::query()
                    ->where('date', '>=', $r['from']->toDateString())
                    ->where('date', '<=', $r['to']->toDateString())),
                'discount_given'
            );

            // USD equivalents via consolidatedUsd() for single-number KPI display.
            $totalRevenueUsd  = $this->consolidatedUsd($revenueByMoneyCol);
            $totalDiscountUsd = $this->consolidatedUsd($discountByMoneyCol);

            $revenueByCurrency  = $revenueByMoneyCol->map(fn($r) => [
                'currency' => $r->currency,
                'revenue'  => round($r->total / 100, 2),
            ])->values()->all();
            $discountByCurrency = $discountByMoneyCol->map(fn($r) => [
                'currency' => $r->currency,
                'discount' => round($r->total / 100, 2),
            ])->values()->all();

            $countryJoin = $countryId ? 'JOIN vendors v ON v.id = fsa.vendor_id' : '';
            $countryWhere = $countryId ? ' AND v.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateString(), $r['to']->toDateString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            $scalars = DB::selectOne("
                SELECT
                    COALESCE(SUM(fsa.units_sold), 0)             AS total_units,
                    ROUND(AVG(fsa.conversion_rate) * 100, 4)     AS avg_conversion_rate,
                    COUNT(DISTINCT fsa.flash_sale_id)             AS total_flash_sales
                FROM flash_sale_analytics fsa
                {$countryJoin}
                WHERE fsa.date >= ? AND fsa.date <= ?
                {$countryWhere}
            ", $bindings);

            $topSales = DB::select("
                SELECT
                    fs.title                                  AS title,
                    fsa.currency,
                    SUM(fsa.units_sold)                      AS units_sold,
                    COALESCE(SUM(fsa.gross_revenue), 0)      AS revenue,
                    ROUND(AVG(fsa.conversion_rate) * 100, 2) AS avg_cvr
                FROM flash_sale_analytics fsa
                JOIN flash_sales fs ON fs.id = fsa.flash_sale_id
                {$countryJoin}
                WHERE fsa.date >= ? AND fsa.date <= ?
                {$countryWhere}
                GROUP BY fs.id, fs.title, fsa.currency
                ORDER BY revenue DESC
                LIMIT 10
            ", $bindings);

            $topVendors = DB::select("
                SELECT
                    v.store_name,
                    fsa.currency,
                    SUM(fsa.units_sold)                      AS units_sold,
                    COALESCE(SUM(fsa.gross_revenue), 0)      AS revenue
                FROM flash_sale_analytics fsa
                JOIN vendors v ON v.id = fsa.vendor_id
                WHERE fsa.date >= ? AND fsa.date <= ?
                {$countryWhere}
                GROUP BY v.id, v.store_name, fsa.currency
                ORDER BY revenue DESC
                LIMIT 10
            ", $bindings);

            return [
                'total_units_sold'       => (int) $scalars->total_units,
                'total_revenue'          => round($totalRevenueUsd, 2),
                'total_revenue_by_currency' => $revenueByCurrency,
                'total_discount'         => round($totalDiscountUsd, 2),
                'total_discount_by_currency' => $discountByCurrency,
                'avg_conversion_rate'    => (float) ($scalars->avg_conversion_rate ?? 0),
                'total_flash_sales'      => (int) $scalars->total_flash_sales,
                'top_performing_sales'   => array_map(fn($row) => [
                    'title'      => $row->title,
                    'currency'   => $row->currency,
                    'units_sold' => (int) $row->units_sold,
                    'revenue'    => round($row->revenue / 100, 2),
                    'avg_cvr'    => (float) $row->avg_cvr,
                ], $topSales),
                'top_performing_vendors' => array_map(fn($row) => [
                    'store_name' => $row->store_name,
                    'currency'   => $row->currency,
                    'units_sold' => (int) $row->units_sold,
                    'revenue'    => round($row->revenue / 100, 2),
                ], $topVendors),
            ];
        });
    }

    // ── Return analytics ──────────────────────────────────────────────────────

    public function returnAnalytics(Request $request): array
    {
        $key = $this->cacheKey('return-analytics', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            $countryJoin = $countryId ? 'JOIN customers c ON c.id = rr.customer_id' : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            $byReason = DB::select("
                SELECT rr.reason, COUNT(*) AS cnt
                FROM return_requests rr
                {$countryJoin}
                WHERE rr.created_at >= ? AND rr.created_at <= ?
                {$countryWhere}
                GROUP BY rr.reason
                ORDER BY cnt DESC
            ", $bindings);

            $byVendor = DB::select("
                SELECT v.store_name, COUNT(rr.id) AS returns_count
                FROM return_requests rr
                JOIN vendors v ON v.id = rr.vendor_id
                {$countryJoin}
                WHERE rr.created_at >= ? AND rr.created_at <= ?
                {$countryWhere}
                GROUP BY v.id, v.store_name
                ORDER BY returns_count DESC
                LIMIT 15
            ", $bindings);

            $byLiability = DB::select("
                SELECT
                    COALESCE(rr.liability, 'unresolved') AS liability,
                    COUNT(*) AS cnt
                FROM return_requests rr
                {$countryJoin}
                WHERE rr.created_at >= ? AND rr.created_at <= ?
                {$countryWhere}
                GROUP BY liability
                ORDER BY cnt DESC
            ", $bindings);

            $monthlyTrend = DB::select("
                SELECT DATE_FORMAT(rr.created_at, '%Y-%m') AS month, COUNT(*) AS cnt
                FROM return_requests rr
                {$countryJoin}
                WHERE rr.created_at >= ? AND rr.created_at <= ?
                {$countryWhere}
                GROUP BY month
                ORDER BY month ASC
            ", $bindings);

            $ordersCountryJoin = $countryId ? 'JOIN customers c ON c.id = o.customer_id' : '';
            $totals = DB::selectOne("
                SELECT
                    COUNT(*) AS total_returns,
                    (SELECT COUNT(DISTINCT o.id) FROM orders o
                     {$ordersCountryJoin}
                     WHERE o.placed_at >= ? AND o.placed_at <= ? AND o.deleted_at IS NULL
                     {$countryWhere}) AS total_orders
                FROM return_requests rr
                {$countryJoin}
                WHERE rr.created_at >= ? AND rr.created_at <= ?
                {$countryWhere}
            ", array_values(array_filter(
                [
                    $r['from']->toDateTimeString(),
                    $r['to']->toDateTimeString(),
                    $countryId ?: null,
                    $r['from']->toDateTimeString(),
                    $r['to']->toDateTimeString(),
                    $countryId ?: null,
                ],
                fn($v) => $v !== null
            )));

            $retRate = $totals->total_orders > 0
                ? round($totals->total_returns / $totals->total_orders * 100, 2)
                : 0;

            return [
                'total_returns' => (int) $totals->total_returns,
                'return_rate' => $retRate,
                'by_reason' => array_map(fn($r) => [
                    'reason' => ucfirst(str_replace('_', ' ', $r->reason)),
                    'count' => (int) $r->cnt,
                ], $byReason),
                'by_vendor' => array_map(fn($r) => [
                    'store_name' => $r->store_name,
                    'count' => (int) $r->returns_count,
                ], $byVendor),
                'by_liability' => array_map(fn($r) => [
                    'liability' => ucfirst($r->liability),
                    'count' => (int) $r->cnt,
                ], $byLiability),
                'monthly_trend' => [
                    'labels' => array_column($monthlyTrend, 'month'),
                    'counts' => array_map(fn($r) => (int) $r->cnt, $monthlyTrend),
                ],
            ];
        });
    }

    // ── Support metrics ───────────────────────────────────────────────────────

    public function supportMetrics(Request $request): array
    {
        $key = $this->cacheKey('support-metrics', $request);
        $ttl = $this->cacheTtl($request->input('period', 'month'));

        return Cache::remember($key, $ttl, function () use ($request) {
            $r = $this->dateRange($request);
            $countryId = $request->input('country_id');
            // requester_user_id is polymorphic (customer or seller); scope to customer-origin
            // tickets whose customer belongs to the filtered country.
            $countryJoin = $countryId
                ? "JOIN customers c ON c.id = st.requester_user_id AND st.requester_role = 'customer'"
                : '';
            $countryWhere = $countryId ? ' AND c.country_id = ?' : '';
            $bindings = array_values(array_filter(
                [$r['from']->toDateTimeString(), $r['to']->toDateTimeString(), $countryId ?: null],
                fn($v) => $v !== null
            ));

            $totals = DB::selectOne("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN st.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
                    ROUND(AVG(CASE WHEN st.first_response_at IS NOT NULL
                        THEN TIMESTAMPDIFF(MINUTE, st.created_at, st.first_response_at)
                    END) / 60.0, 2) AS avg_first_response_hours,
                    ROUND(AVG(CASE WHEN st.resolved_at IS NOT NULL
                        THEN TIMESTAMPDIFF(MINUTE, st.created_at, st.resolved_at)
                    END) / 60.0, 2) AS avg_resolution_hours,
                    ROUND(AVG(st.satisfaction_rating), 2) AS avg_satisfaction
                FROM support_tickets st
                {$countryJoin}
                WHERE st.created_at >= ? AND st.created_at <= ?
                {$countryWhere}
            ", $bindings);

            $byCategory = DB::select("
                SELECT st.category, COUNT(*) AS cnt
                FROM support_tickets st
                {$countryJoin}
                WHERE st.created_at >= ? AND st.created_at <= ?
                {$countryWhere}
                GROUP BY st.category
                ORDER BY cnt DESC
            ", $bindings);

            $byPriority = DB::select("
                SELECT st.priority, COUNT(*) AS cnt
                FROM support_tickets st
                {$countryJoin}
                WHERE st.created_at >= ? AND st.created_at <= ?
                {$countryWhere}
                GROUP BY st.priority
                ORDER BY FIELD(st.priority, 'urgent', 'high', 'normal', 'low')
            ", $bindings);

            $trend = DB::select("
                SELECT DATE(st.created_at) AS date, COUNT(*) AS created,
                       SUM(CASE WHEN st.resolved_at IS NOT NULL AND DATE(st.resolved_at) = DATE(st.created_at) THEN 1 ELSE 0 END) AS resolved
                FROM support_tickets st
                {$countryJoin}
                WHERE st.created_at >= ? AND st.created_at <= ?
                {$countryWhere}
                GROUP BY DATE(st.created_at)
                ORDER BY date ASC
            ", $bindings);

            return [
                'total' => (int) $totals->total,
                'open_tickets' => (int) $totals->open_tickets,
                'avg_first_response_hours' => (float) ($totals->avg_first_response_hours ?? 0),
                'avg_resolution_hours' => (float) ($totals->avg_resolution_hours ?? 0),
                'avg_satisfaction' => (float) ($totals->avg_satisfaction ?? 0),
                'by_category' => array_map(fn($r) => [
                    'category' => ucfirst(str_replace('_', ' ', $r->category)),
                    'count' => (int) $r->cnt,
                ], $byCategory),
                'by_priority' => array_map(fn($r) => [
                    'priority' => ucfirst($r->priority),
                    'count' => (int) $r->cnt,
                ], $byPriority),
                'resolution_trend' => [
                    'labels' => array_column($trend, 'date'),
                    'created' => array_map(fn($r) => (int) $r->created, $trend),
                    'resolved' => array_map(fn($r) => (int) $r->resolved, $trend),
                ],
            ];
        });
    }
}
