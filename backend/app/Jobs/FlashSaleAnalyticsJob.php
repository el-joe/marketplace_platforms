<?php

namespace App\Jobs;

use App\Models\FlashSale;
use App\Models\FlashSaleAnalytic;
use App\Models\FlashSaleSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FlashSaleAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly FlashSale $sale)
    {
    }

    public function handle(): void
    {
        $submissionIds = FlashSaleSubmission::where('flash_sale_id', $this->sale->id)
            ->pluck('id');

        foreach ($submissionIds as $submissionId) {
            $submission = FlashSaleSubmission::find($submissionId);
            if (!$submission) {
                continue;
            }

            $orders = DB::table('flash_sale_orders')
                ->where('flash_sale_submission_id', $submissionId)
                ->selectRaw('
                    SUM(quantity) as units_sold,
                    SUM(flash_price * quantity) as gross_revenue,
                    SUM(original_price * quantity) as revenue_at_normal_price,
                    SUM(discount_amount * quantity) as discount_given,
                    currency
                ')
                ->groupBy('currency')
                ->first();

            if (!$orders) {
                continue;
            }

            $commissionRate = $this->sale->commission_override_pct ?? 10.0;
            $commission = (int) round($orders->gross_revenue * $commissionRate / 100);
            $vendorPayout = (int) $orders->gross_revenue - $commission;

            FlashSaleAnalytic::create([
                'flash_sale_id' => $this->sale->id,
                'flash_sale_submission_id' => $submissionId,
                'vendor_id' => $submission->vendor_id,
                'date' => $this->sale->sale_ends_at?->toDateString() ?? now()->toDateString(),
                'units_sold' => (int) $orders->units_sold,
                'currency' => $orders->currency,
                'gross_revenue' => (int) $orders->gross_revenue,
                'revenue_at_normal_price' => (int) $orders->revenue_at_normal_price,
                'discount_given' => (int) $orders->discount_given,
                'platform_commission' => $commission,
                'vendor_payout' => $vendorPayout,
                'views' => 0,
                'add_to_cart_count' => 0,
                'conversion_rate' => 0,
            ]);
        }
    }
}
