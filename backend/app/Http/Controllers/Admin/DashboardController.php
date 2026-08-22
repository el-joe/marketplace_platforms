<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\VendorGlobalStatus;
use App\Enums\WalletWithdrawalRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Vendor;
use App\Models\WarehouseInventory;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $countries = Country::where('is_active', 1)->orderBy('name_en')->get();
        $defaultCountryId = auth()->guard('admin')->user()?->country_id;

        return view('admin.dashboard.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard')],
            ],
            'countries' => $countries,
            'defaultCountryId' => $defaultCountryId,
        ]);
    }

    /**
     * AJAX — stat cards.
     * Accepts: period (today|week|month), country_id
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'week');
        $countryId = $request->input('country_id');

        [$currentStart, $currentEnd, $prevStart, $prevEnd] = $this->periodDates($period);

        $current = $this->fetchStats($currentStart, $currentEnd, $countryId);
        $previous = $this->fetchStats($prevStart, $prevEnd, $countryId);

        $changes = [];
        foreach (['gmv', 'orders', 'revenue', 'sellers'] as $key) {
            $prev = $previous[$key] ?? 0;
            $curr = $current[$key] ?? 0;
            $changes[$key] = $prev > 0
                ? round((($curr - $prev) / $prev) * 100, 1)
                : ($curr > 0 ? 100.0 : 0.0);
        }

        $currency = $countryId
            ? Country::find($countryId)?->currency_code
            : null;

        return response()->json([
            'data' => [
                'gmv' => $this->formatMoney($current['gmv'], $currency),
                'orders' => number_format($current['orders']),
                'revenue' => $this->formatMoney($current['revenue'], $currency),
                'sellers' => number_format($current['sellers']),
                'changes' => $changes,
            ],
        ]);
    }

    /**
     * AJAX — revenue line chart.
     * Accepts: range (7|30|90), country_id
     */
    public function revenueChart(Request $request): JsonResponse
    {
        $days = (int) $request->input('range', 30);
        $days = in_array($days, [7, 30, 90]) ? $days : 30;

        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $countryId = $request->input('country_id');

        // Build date-bucketed GMV grouped by currency to avoid blending amounts across currencies.
        // When country_id is supplied the join guarantees a single currency; without it each
        // currency produces its own row so the chart can render separate series.
        $orderRows = Order::query()
            ->join('customers as c', 'c.id', '=', 'orders.customer_id')
            ->selectRaw('DATE(orders.placed_at) as date, orders.currency, SUM(orders.total) as gmv')
            ->whereBetween('orders.placed_at', [$start, $end])
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value])
            ->when($countryId, fn($q) => $q->where('c.country_id', $countryId))
            ->groupBy('date', 'orders.currency')
            ->get()
            ->groupBy('date');

        // Commission is sourced from order_items.commission_amount in this schema.
        $commRows = collect();
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'commission_amount')) {
            $commRows = OrderItem::query()->from('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('customers as c', 'c.id', '=', 'o.customer_id')
                ->selectRaw('DATE(o.placed_at) as date, o.currency, SUM(oi.commission_amount) as commission')
                ->whereBetween('o.placed_at', [$start, $end])
                ->whereNotIn('o.status', [OrderStatus::Cancelled->value])
                ->when($countryId, fn($q) => $q->where('c.country_id', $countryId))
                ->groupBy('date', 'o.currency')
                ->get()
                ->groupBy('date');
        }

        $labelFmt = $days > 30 ? 'M d' : 'd M';

        // Determine currencies present in this result set.
        $allCurrencies = $orderRows->flatMap(fn($rows) => $rows->pluck('currency'))
            ->unique()->sort()->values()->all();

        // Single-currency path (country filter applied, or all data happens to be one currency).
        if (count($allCurrencies) <= 1) {
            $currency = $allCurrencies[0] ?? null;
            $labels = $gmvData = $commData = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $labels[]   = now()->subDays($i)->format($labelFmt);
                $gmvData[]  = (int) ($orderRows->get($date)?->first()?->gmv ?? 0);
                $commData[] = (int) ($commRows->get($date)?->first()?->commission ?? 0);
            }
            return response()->json([
                'data' => [
                    'labels'     => $labels,
                    'gmv'        => $gmvData,
                    'commission' => $commData,
                    'currency'   => $currency,
                ],
            ]);
        }

        // Multi-currency path: one dataset per currency so the chart renders separate lines.
        $allDates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $allDates[] = now()->subDays($i)->format('Y-m-d');
        }
        $labels = array_map(fn($d) => Carbon::parse($d)->format($labelFmt), $allDates);
        $datasets = [];
        foreach ($allCurrencies as $currency) {
            $gmvData = $commData = [];
            foreach ($allDates as $date) {
                $gmvRow  = collect($orderRows[$date] ?? [])->firstWhere('currency', $currency);
                $commRow = collect($commRows[$date] ?? [])->firstWhere('currency', $currency);
                $gmvData[]  = (int) ($gmvRow->gmv ?? 0);
                $commData[] = (int) ($commRow->commission ?? 0);
            }
            $datasets[$currency] = ['gmv' => $gmvData, 'commission' => $commData];
        }
        return response()->json([
            'data' => [
                'labels'   => $labels,
                'datasets' => $datasets,
            ],
        ]);
    }

    /**
     * AJAX — donut chart: orders broken down by status.
     */
    public function ordersByStatus(Request $request): JsonResponse
    {
        $period = $request->input('period', 'week');
        $countryId = $request->input('country_id');

        [$start, $end] = $this->periodWindowDates($period);

        // Maps the 6 UI labels to the actual orders.status enum values
        $statusGroups = [
            'pending'    => ['placed'],
            'processing' => ['confirmed', 'partially_shipped'],
            'shipped'    => ['shipped', 'partially_delivered'],
            'delivered'  => ['delivered', 'completed'],
            'cancelled'  => ['cancelled'],
            'refunded'   => ['refunded'],
        ];

        $colors = [
            'pending'    => '#f59e0b',
            'processing' => '#3b82f6',
            'shipped'    => '#8b5cf6',
            'delivered'  => '#22c55e',
            'cancelled'  => '#ef4444',
            'refunded'   => '#6b7280',
        ];

        $base = Order::query()
            ->whereBetween('placed_at', [$start, $end])
            ->when($countryId, fn($q) => $q->whereHas(
                'customer', fn($cq) => $cq->where('country_id', $countryId)
            ));

        $labels = $values = $colorList = [];

        foreach ($statusGroups as $key => $statuses) {
            $count = (int) (clone $base)->whereIn('status', $statuses)->count();
            $labels[] = __("admin.dashboard.order_status_groups.{$key}");
            $values[] = $count;
            $colorList[] = $colors[$key];
        }

        return response()->json(['data' => [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colorList,
        ]]);
    }

    /**
     * AJAX — last 10 orders for the activity feed.
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $countryId = $request->input('country_id');

        // Use the Eloquent relationship so SoftDeletes scope is applied correctly.
        $orders = Order::with('customer')
            ->when($countryId, fn($q) => $q->whereHas(
                'customer', fn($cq) => $cq->where('country_id', $countryId)
            ))
            ->orderByDesc('placed_at')
            ->limit(10)
            ->get()
            ->map(fn($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number ?? '#—',
                'customer_name' => $order->customer?->name ?? __('admin.dashboard.guest'),
                'total' => number_format($order->total / 100, 2) . ' ' . ($order->currency ?? ''),
                'status' => $order->status->value,
                'status_label' => __("common.order_status.{$order->status->value}"),
                'created_at' => Carbon::parse($order->placed_at)->diffForHumans(),
            ]);

        return response()->json(['data' => $orders]);
    }

    /**
     * AJAX — top 10 sellers by GMV (last 30 days).
     * Accepts: country_id — mandatory to ensure single-currency sums.
     * Without a country filter, vendors span multiple currencies; SUM(line_total)
     * across currencies is meaningless, so we return per-currency breakdowns instead.
     */
    public function topSellers(Request $request): JsonResponse
    {
        $countryId = $request->input('country_id');

        // GROUP BY currency so amounts are never blended across currencies.
        $sellers = OrderItem::query()->from('order_items as oi')
            ->select([
                'v.id',
                'v.business_name',
                'o.currency',
                DB::raw('SUM(oi.line_total) as gmv'),
                DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
                DB::raw('AVG(v.store_rating_avg) as rating'),
            ])
            ->join('vendors as v', 'v.id', '=', 'oi.vendor_id')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.created_at', '>=', now()->subDays(30))
            ->whereNotIn('o.status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value])
            ->when($countryId, fn($q) => $q->where('c.country_id', $countryId))
            ->groupBy('v.id', 'v.business_name', 'v.store_rating_avg', 'o.currency')
            ->orderByDesc('gmv')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'business_name' => $row->business_name,
                'currency' => $row->currency,
                'gmv' => $this->formatMoney($row->gmv, $row->currency),
                'order_count' => (int) $row->order_count,
                'rating' => $row->rating ? number_format($row->rating, 1) : '—',
            ]);

        return response()->json(['data' => $sellers]);
    }

    /**
     * AJAX — counts of items awaiting admin action.
     */
    public function pendingItems(): JsonResponse
    {
        $counts = [
            'products' => Product::query()->where('status', 'pending_review')->count(),
            'vendors' => Vendor::query()->whereIn('global_status', [VendorGlobalStatus::Pending->value, VendorGlobalStatus::UnderReview->value])->count(),
            'disputes' => Dispute::query()->where('status', DisputeStatus::Open->value)->count(),
            'withdrawals' => Schema::hasTable('withdrawal_requests')
                ? WithdrawalRequest::query()->where('status', WalletWithdrawalRequestStatus::Pending->value)->count()
                : Payout::query()->whereIn('status', ['pending', 'requested'])->count(),
            'returns' => ReturnRequest::query()->where('status', 'pending')->count(),
        ];

        $total = array_sum($counts);

        return response()->json(['data' => array_merge($counts, ['total' => $total])]);
    }

    /**
     * AJAX — products with stock ≤ 5.
     */
    public function lowStock(): JsonResponse
    {
        $products = WarehouseInventory::query()->from('warehouse_inventories as wi')
            ->select([
                'p.id as product_id',
                'p.name_en as name',
                'wi.quantity_available as stock',
                'v.business_name as vendor',
            ])
            ->join('vendor_listings as vl', 'vl.id', '=', 'wi.vendor_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('vendors as v', 'v.id', '=', 'vl.vendor_id')
            ->where('wi.quantity_available', '<=', 5)
            ->whereIn('vl.status', ['active', 'approved'])
            ->orderBy('wi.quantity_available')
            ->limit(20)
            ->get()
            ->map(fn($row) => [
                'id' => $row->product_id,
                'name' => $row->name,
                'stock' => (int) $row->stock,
                'vendor' => $row->vendor ?? '—',
            ]);

        return response()->json(['data' => $products]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /** Returns [currentStart, currentEnd, previousStart, previousEnd] as Carbon instances. */
    private function periodDates(string $period): array
    {
        [$start, $end] = $this->periodWindowDates($period);

        $lengthSeconds = $start->diffInSeconds($end);
        $prevEnd = $start->clone()->subSecond();
        $prevStart = $prevEnd->clone()->subSeconds($lengthSeconds);

        return [$start, $end, $prevStart, $prevEnd];
    }

    /** Returns [start, end] for the given period. */
    private function periodWindowDates(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfWeek(), now()->endOfWeek()],
        };
    }

    /** Fetch raw numeric stats for a time window. */
    private function fetchStats(Carbon $start, Carbon $end, ?string $countryId): array
    {
        $gmv = (int) Order::query()
            ->whereBetween('placed_at', [$start, $end])
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->when($countryId, fn($q) => $q->whereHas(
                'customer', fn($cq) => $cq->where('country_id', $countryId)
            ))
            ->sum('total');

        $orders = (int) Order::query()
            ->whereBetween('placed_at', [$start, $end])
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->when($countryId, fn($q) => $q->whereHas(
                'customer', fn($cq) => $cq->where('country_id', $countryId)
            ))
            ->count();

        $revenue = 0;
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'commission_amount')) {
            $revenue = (int) OrderItem::query()->from('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereBetween('o.placed_at', [$start, $end])
                ->whereNotIn('o.status', [OrderStatus::Cancelled->value])
                ->when($countryId, function ($q) use ($countryId) {
                    $q->whereExists(function ($sub) use ($countryId) {
                        $sub->from('customers')
                            ->whereColumn('customers.id', 'o.customer_id')
                            ->where('customers.country_id', $countryId);
                    });
                })
                ->sum('oi.commission_amount');
        }

        $sellers = (int) Vendor::query()
            ->where('global_status', VendorGlobalStatus::Active->value)
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->count();

        return compact('gmv', 'orders', 'revenue', 'sellers');
    }

    /** Format a cents integer. Prepends currency code when known. */
    private function formatMoney(int|float|null $cents, ?string $currency = null): string
    {
        $amount = number_format((int) $cents / 100, 2);
        return $currency ? "{$amount} {$currency}" : $amount;
    }
}
