<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OrderConfirmationEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['customer', 'subOrders.items'])->find($this->orderId);

        if (! $order) {
            Log::warning("OrderConfirmationEmailJob: order {$this->orderId} not found.");
            return;
        }

        // TODO: dispatch Mailable once mail templates are ready
        Log::info("Order confirmation email queued for order {$order->order_number} → {$order->customer->email}");
    }
}
