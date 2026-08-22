<?php

namespace App\Jobs;

use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerDeliveredNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly string $subOrderId) {}

    public function handle(): void
    {
        $subOrder = SubOrder::with('order.customer')->find($this->subOrderId);

        if (! $subOrder) {
            Log::warning('CustomerDeliveredNotificationJob: sub_order not found', ['id' => $this->subOrderId]);
            return;
        }

        $customer = $subOrder->order?->customer;

        if (! $customer) {
            return;
        }

        $customer->notify(new \App\Notifications\Customer\OrderDelivered($subOrder));
    }
}
