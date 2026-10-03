<?php

namespace App\Observers;

use App\Jobs\GenerateEntityQrCodeJob;
use App\Models\Vendor;

class VendorObserver
{
    public function created(Vendor $vendor): void
    {
        $this->dispatchQrJob($vendor);
    }

    public function updated(Vendor $vendor): void
    {
        if ($vendor->wasChanged(['store_slug', 'store_name', 'name'])) {
            $this->dispatchQrJob($vendor);
        }
    }

    private function dispatchQrJob(Vendor $vendor): void
    {
        if (! $vendor->store_slug) {
            return;
        }

        dispatch(new GenerateEntityQrCodeJob(
            model: $vendor,
            scanUrl: route('qr.scan.vendor', ['slug' => $vendor->store_slug]),
            label: $vendor->store_name ?? $vendor->name,
            storagePath: "qr/vendors/{$vendor->store_slug}.png",
        ));
    }
}
