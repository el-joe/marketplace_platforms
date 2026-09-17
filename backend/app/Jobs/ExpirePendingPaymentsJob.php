<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Checkout\CheckoutRollbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * enhancement.md P-05 task 5: gateway (redirect / direct card) orders that
 * never received a webhook or browser callback — the customer closed the
 * tab, the gateway never fired, etc. — would otherwise sit 'pending'
 * forever, holding stock/coupon/wallet/loyalty reservations hostage.
 * Rolls them back via CheckoutRollbackService once they've been pending
 * longer than the threshold. COD and bank_transfer orders are exempt:
 * they are expected to sit pending until delivery / admin confirmation.
 */
class ExpirePendingPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $thresholdMinutes = 30) {}

    public function handle(CheckoutRollbackService $rollbackService): void
    {
        $cutoff = now()->subMinutes($this->thresholdMinutes);

        Order::where('payment_status', 'pending')
            ->whereNotIn('payment_gateway_code', ['cod', 'bank_transfer', 'wallet'])
            ->where('placed_at', '<', $cutoff)
            ->where('status', '!=', 'cancelled')
            ->chunkById(100, function ($orders) use ($rollbackService) {
                foreach ($orders as $order) {
                    $order->update(['payment_status' => 'failed', 'status' => 'cancelled', 'cancelled_at' => now()]);
                    $rollbackService->rollback($order, 'Pending payment expired');

                    Log::info('ExpirePendingPaymentsJob: rolled back expired pending order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);
                }
            });
    }
}
