<?php

namespace App\Jobs;

use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use App\Models\VendorAcquisitionCommission;
use App\Models\VendorAcquisitionCommissionEarning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessAcquisitionCommissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $prevMonth = now()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd = $prevMonth->copy()->endOfMonth();

        VendorAcquisitionCommission::where('status', 'active')
            ->where('valid_from', '<=', $prevMonthEnd)
            ->where('valid_until', '>=', $prevMonth)
            ->each(function (VendorAcquisitionCommission $commission) use ($prevMonth, $prevMonthEnd) {
                $this->processCommission($commission, $prevMonth, $prevMonthEnd);
            });
    }

    private function processCommission(VendorAcquisitionCommission $commission, Carbon $prevMonth, Carbon $prevMonthEnd): void
    {
        // sub_orders has no dedicated completed_at column, so updated_at is used as the
        // completion timestamp (it is set when status transitions to 'completed').
        $baseQuery = SubOrder::where('vendor_id', $commission->vendor_id)
            ->where('status', SubOrderStatus::Completed->value)
            ->whereBetween('updated_at', [$prevMonth, $prevMonthEnd]);

        $monthlyCount = (clone $baseQuery)->count();

        if ($monthlyCount >= $commission->monthly_min_sales) {
            $orders = (clone $baseQuery)
                ->orderBy('updated_at')
                ->take($commission->monthly_max_sales)
                ->get();

            DB::transaction(function () use ($orders, $commission, $prevMonth) {
                $orderCount = 0;
                foreach ($orders as $order) {
                    $orderCount++;
                    $amount = intdiv($order->subtotal * $commission->commission_rate, 10000);

                    $earning = VendorAcquisitionCommissionEarning::firstOrCreate(
                        ['commission_id' => $commission->id, 'sub_order_id' => $order->id],
                        [
                            'month' => $prevMonth->toDateString(),
                            'order_count_in_month' => $orderCount,
                            'amount' => $amount,
                            'currency' => $commission->currency,
                        ],
                    );

                    if ($earning->wasRecentlyCreated) {
                        $commission->increment('total_earned', $amount);
                    }
                }
            });
        }

        if (now()->toDateString() > $commission->valid_until->toDateString()) {
            $commission->update(['status' => 'expired']);
        }
    }
}
