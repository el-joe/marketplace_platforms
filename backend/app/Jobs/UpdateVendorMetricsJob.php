<?php

namespace App\Jobs;

use App\Models\Vendor;
use App\Models\VendorStrike;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateVendorMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string|null  $vendorId  Pass a specific vendor UUID to update only that vendor; null = all vendors.
     */
    public function __construct(public readonly ?string $vendorId = null)
    {
    }

    public function handle(): void
    {
        $query = Vendor::query()->whereIn('global_status', ['active', 'suspended', 'under_review']);

        if ($this->vendorId) {
            $query->where('id', $this->vendorId);
        }

        // Auto-expire stale strikes before aggregating
        VendorStrike::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);

        $query->chunk(50, function ($vendors) {
            foreach ($vendors as $vendor) {
                $this->updateVendor($vendor);
            }
        });
    }

    private function updateVendor(Vendor $vendor): void
    {
        // ── GMV & total orders (from sub_orders) ─────────────────────────────
        // A vendor is scoped to one country_id and therefore one currency, so the SUM is safe
        // in practice. JOIN to orders and GROUP BY currency is an explicit safeguard so that
        // any future cross-border vendor data produces separate rows rather than blending.
        $metricsRows = DB::table('sub_orders')
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->where('sub_orders.vendor_id', $vendor->id)
            ->whereIn('sub_orders.status', ['completed', 'delivered'])
            ->selectRaw('orders.currency, COALESCE(SUM(sub_orders.vendor_payout), 0) as gmv, COUNT(*) as total_orders')
            ->groupBy('orders.currency')
            ->get();

        $orderMetrics = (object) [
            'gmv'          => $metricsRows->sum('gmv'),
            'total_orders' => $metricsRows->sum('total_orders'),
        ];

        // ── Return rate ───────────────────────────────────────────────────────
        $totalOrders = (int) $orderMetrics->total_orders;
        $returnedCount = DB::table('return_requests')
            ->join('sub_orders', 'return_requests.sub_order_id', '=', 'sub_orders.id')
            ->where('sub_orders.vendor_id', $vendor->id)
            ->whereIn('return_requests.status', ['approved', 'completed'])
            ->count();

        $returnRatePct = $totalOrders > 0
            ? round(($returnedCount / $totalOrders) * 100, 2)
            : 0.00;

        // ── Cancellation rate ─────────────────────────────────────────────────
        $cancelledCount = DB::table('sub_orders')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'cancelled')
            ->count();

        $allOrdersCount = DB::table('sub_orders')
            ->where('vendor_id', $vendor->id)
            ->count();

        $cancellationRatePct = $allOrdersCount > 0
            ? round(($cancelledCount / $allOrdersCount) * 100, 2)
            : 0.00;

        // ── Store rating ──────────────────────────────────────────────────────
        $ratingMetrics = DB::table('reviews')
            ->where('vendor_id', $vendor->id)
            ->whereNull('deleted_at')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as rating_count')
            ->first();

        // ── Active strikes ────────────────────────────────────────────────────
        $activeStrikes = $vendor->activeStrikes()->count();

        $vendor->updateQuietly([
            'total_sales' => round($orderMetrics->gmv / 100, 2), // stored in cents
            'total_orders' => $totalOrders,
            'return_rate_pct' => $returnRatePct,
            'cancellation_rate_pct' => $cancellationRatePct,
            'store_rating_avg' => round((float) $ratingMetrics->avg_rating, 2),
            'store_rating_count' => (int) $ratingMetrics->rating_count,
            'strikes_count' => $activeStrikes,
        ]);
    }
}
