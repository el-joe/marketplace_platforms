<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyVendorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $orderId,
        public readonly string $vendorId,
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        $vendor = Vendor::find($this->vendorId);

        if (! $order || ! $vendor) {
            Log::warning("NotifyVendorJob: order or vendor not found. order={$this->orderId} vendor={$this->vendorId}");
            return;
        }

        // TODO: push notification / email to vendor dashboard once notification system is ready
        Log::info("Vendor {$vendor->store_name} notified of new order {$order->order_number}");
    }
}
