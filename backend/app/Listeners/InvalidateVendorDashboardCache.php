<?php

namespace App\Listeners;

use App\Events\SubOrderPlaced;
use Illuminate\Support\Facades\Cache;

class InvalidateVendorDashboardCache
{
    public function handle(SubOrderPlaced $event): void
    {
        Cache::forget("vendor.dashboard.{$event->subOrder->vendor_id}");
    }
}
