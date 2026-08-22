<?php

namespace App\Jobs;

use App\Enums\SubOrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCompleteOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days after delivery before auto-completing a sub-order.
     */
    private const AUTO_COMPLETE_DAYS = 14;

    public function handle(): void
    {
        $cutoff = now()->subDays(self::AUTO_COMPLETE_DAYS);

        // Find eligible sub-orders: delivered and past the auto-complete window
        $subOrders = SubOrder::with('order')
            ->whereNull('deleted_at')
            ->where('status', SubOrderStatus::Delivered->value)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->get();

        if ($subOrders->isEmpty()) {
            return;
        }

        $affectedOrderIds = $subOrders->pluck('order_id')->unique()->values();

        DB::transaction(function () use ($subOrders, $affectedOrderIds) {
            foreach ($subOrders as $subOrder) {
                $subOrder->update(['status' => 'completed']);

                OrderStatusHistory::create([
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'from_status' => 'delivered',
                    'to_status' => 'completed',
                    'changed_by_admin_id' => null,
                    'reason' => 'Auto-completed after ' . self::AUTO_COMPLETE_DAYS . ' days post-delivery.',
                    'metadata' => ['source' => 'AutoCompleteOrdersJob'],
                ]);
            }

            // For each affected parent order, check if all sub-orders are now completed
            foreach ($affectedOrderIds as $orderId) {
                $allCompleted = SubOrder::where('order_id', $orderId)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
                    ->doesntExist();

                if ($allCompleted) {
                    $order = \App\Models\Order::find($orderId);
                    if ($order && $order->status->value !== 'completed') {
                        $prevStatus = $order->status->value;
                        $order->update(['status' => 'completed', 'completed_at' => now()]);

                        OrderStatusHistory::create([
                            'order_id' => $orderId,
                            'sub_order_id' => null,
                            'from_status' => $prevStatus,
                            'to_status' => 'completed',
                            'changed_by_admin_id' => null,
                            'reason' => 'All sub-orders completed. Auto-completed.',
                            'metadata' => ['source' => 'AutoCompleteOrdersJob'],
                        ]);
                    }
                }
            }
        });

        Log::info('AutoCompleteOrdersJob: completed ' . $subOrders->count() . ' sub-order(s) across ' . $affectedOrderIds->count() . ' order(s).');
    }
}
