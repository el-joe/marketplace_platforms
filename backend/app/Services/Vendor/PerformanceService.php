<?php

namespace App\Services\Vendor;

use App\Models\Review;
use App\Models\SubOrder;
use App\Models\Vendor;
use Illuminate\Support\Carbon;

class PerformanceService
{
    // Confirmed columns from vendors table: store_rating_avg, cancellation_rate_pct,
    // sla_compliance_pct, return_rate_pct, store_rating_count.
    // on_time_ship_rate and response_time_avg do NOT exist — omitted intentionally.

    public function getSummary(Vendor $vendor, int $days = 30): array
    {
        return [
            'store_rating_avg'       => round((float) $vendor->store_rating_avg, 2),
            'total_reviews'          => (int) $vendor->store_rating_count,
            'cancellation_rate_pct'  => round((float) $vendor->cancellation_rate_pct, 2),
            'sla_compliance_pct'     => round((float) $vendor->sla_compliance_pct, 2),
            'return_rate_pct'        => round((float) $vendor->return_rate_pct, 2),
            'period_days'            => $days,
            'period_comparison'      => $this->buildPeriodComparison($vendor, $days),
        ];
    }

    private function buildPeriodComparison(Vendor $vendor, int $days): array
    {
        $periods = [30 => null, 60 => null, 90 => null];

        foreach (array_keys($periods) as $d) {
            $from  = now()->subDays($d);
            $base  = SubOrder::where('vendor_id', $vendor->id)
                        ->where('created_at', '>=', $from);

            $total        = (clone $base)->count();
            $cancelled    = (clone $base)->where('status', 'cancelled')->count();
            $cancellationRate = $total > 0 ? round($cancelled / $total * 100, 2) : 0;

            $avgRating = Review::whereHas('vendorListing', fn ($q) => $q->where('vendor_id', $vendor->id))
                ->where('created_at', '>=', $from)
                ->where('status', 'published')
                ->avg('rating');

            $periods[$d] = [
                'days'                  => $d,
                'cancellation_rate_pct' => $cancellationRate,
                'avg_rating'            => $avgRating ? round((float) $avgRating, 2) : null,
                'total_orders'          => $total,
            ];
        }

        return array_values($periods);
    }
}
