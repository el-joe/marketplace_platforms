<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FraudDetectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $orderId) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning("FraudDetectionJob: order {$this->orderId} not found.");
            return;
        }

        // TODO: implement fraud scoring logic; update orders.risk_score
        Log::info("Fraud detection queued for order {$order->order_number}");
    }
}
