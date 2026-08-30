<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    /**
     * Resolve start/end Carbon dates from the request period parameter.
     * Returns [$start, $end] — both Carbon instances.
     */
    private function periodBounds(Request $request): array
    {
        $period = $request->input('period', 'month');

        if ($period === 'custom') {
            $request->validate([
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            ]);

            $start = Carbon::parse($request->input('start_date'))->startOfDay();
            $end = Carbon::parse($request->input('end_date'))->endOfDay();

            // Cap custom range to 366 days for performance
            if ($start->diffInDays($end) > 366) {
                $start = $end->copy()->subDays(365)->startOfDay();
            }

            return [$start, $end];
        }

        return match ($period) {
            'week' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'quarter' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()], // month
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('partner.performance.index');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stats (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();

        [$start, $end] = $this->periodBounds($request);

        // ── Order counts ──────────────────────────────────────────────────────

        $totalOrders = SubOrder::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $cancelledOrders = SubOrder::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'cancelled')
            ->count();

        // ── GMV (vendor_payout, bigint cents) ─────────────────────────────────

        $gmvCents = SubOrder::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['delivered', 'completed'])
            ->sum('vendor_payout');

        $gmv = round($gmvCents, 2);
        $avgOrderValue = $totalOrders > 0 ? round($gmvCents / $totalOrders, 2) : 0.0;

        // ── SLA compliance ────────────────────────────────────────────────────

        $slaBase = SubOrder::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('sla_ship_deadline');
        $slaTotalCount = (clone $slaBase)->count();
        $slaGoodCount = (clone $slaBase)->where('sla_breached', false)->count();
        $slaCompliance = $slaTotalCount > 0
            ? round($slaGoodCount / $slaTotalCount * 100, 1)
            : null;

        // ── Cancellation rate ─────────────────────────────────────────────────

        $cancellationRate = $totalOrders > 0
            ? round($cancelledOrders / $totalOrders * 100, 1)
            : 0.0;

        // ── Return rate ───────────────────────────────────────────────────────

        $returnCount = ReturnRequest::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['rejected'])
            ->count();

        $returnRate = $totalOrders > 0
            ? round($returnCount / $totalOrders * 100, 1)
            : 0.0;

        // ── Store rating ──────────────────────────────────────────────────────

        $vendor = Vendor::find($vendorId, ['store_rating_avg', 'store_rating_count']);
        $ratingAvg = $vendor ? (float) $vendor->store_rating_avg : null;
        $ratingCount = $vendor ? (int) $vendor->store_rating_count : 0;

        // ── Revenue chart — daily GMV ─────────────────────────────────────────

        $dailyRevenue = SubOrder::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['delivered', 'completed'])
            ->selectRaw('DATE(created_at) as day, SUM(vendor_payout) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        // Fill every day in the range (no gaps for Chart.js)
        $revenueChart = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $revenueChart[] = [
                'date' => $dateStr,
                'revenue' => isset($dailyRevenue[$dateStr])
                    ? round($dailyRevenue[$dateStr]->total, 2)
                    : 0,
            ];
            $cursor->addDay();
        }

        // ── Top 5 products by revenue ─────────────────────────────────────────

        $topProductRows = OrderItem::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('product_variant_id, SUM(line_total) as revenue, SUM(quantity) as units_sold, COUNT(DISTINCT sub_order_id) as orders_count')
            ->groupBy('product_variant_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('productVariant.product')
            ->get();

        $topProducts = $topProductRows->map(fn(OrderItem $item) => [
            'product_name' => $item->productVariant?->product?->name_ar
                ?? $item->productVariant?->product?->name_en
                ?? '—',
            'revenue' => round($item->revenue, 2),
            'units_sold' => (int) $item->units_sold,
            'orders_count' => (int) $item->orders_count,
        ])->all();

        // ── Reviews breakdown — 1★ to 5★ ─────────────────────────────────────

        $rawBreakdown = Review::whereHas('vendorListing', function ($q) use ($vendorId) {
            $q->where('vendor_id', $vendorId);
        })
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'approved')
            ->selectRaw('rating, COUNT(*) as cnt')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get()
            ->mapWithKeys(fn($r) => [(string) (int) $r->rating => (int) $r->cnt])
            ->all();

        // Ensure all 5 stars are present (fill zeros)
        $reviewsBreakdown = [];
        for ($star = 1; $star <= 5; $star++) {
            $reviewsBreakdown[(string) $star] = $rawBreakdown[(string) $star] ?? 0;
        }
        $totalReviews = array_sum($reviewsBreakdown);

        // ── Active strikes ─────────────────────────────────────────────────────

        $activeStrikes = \App\Models\VendorStrike::where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->count();

        // ── Flash sale summary ────────────────────────────────────────────────

        $flashStats = \App\Models\FlashSaleAnalytic::where('vendor_id', $vendorId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('SUM(units_sold) as units, SUM(gross_revenue) as revenue, SUM(views) as views')
            ->first();

        return response()->json([
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'gmv' => $gmv,
            'orders_count' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
            'sla_compliance' => $slaCompliance,
            'return_rate' => $returnRate,
            'cancellation_rate' => $cancellationRate,
            'rating_avg' => $ratingAvg,
            'rating_count' => $ratingCount,
            'active_strikes' => $activeStrikes,
            'flash_units_sold' => (int) ($flashStats->units ?? 0),
            'flash_revenue' => round(($flashStats->revenue ?? 0), 2),
            'revenue_chart' => $revenueChart,
            'top_products' => $topProducts,
            'reviews_breakdown' => $reviewsBreakdown,
            'total_reviews' => $totalReviews,
        ]);
    }
}
