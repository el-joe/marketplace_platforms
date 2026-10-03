<?php

namespace App\Observers;

use App\Jobs\GenerateEntityQrCodeJob;
use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->dispatchQrJob($product);
    }

    public function updated(Product $product): void
    {
        // Only regenerate if the fields that affect the QR content changed
        if ($product->wasChanged(['slug', 'name_ar', 'name_en'])) {
            $this->dispatchQrJob($product);
        }
    }

    private function dispatchQrJob(Product $product): void
    {
        if (! $product->slug) {
            return;
        }

        dispatch(new GenerateEntityQrCodeJob(
            model: $product,
            scanUrl: route('qr.scan.product', ['slug' => $product->slug]),
            label: $product->name_ar ?? $product->name_en ?? $product->slug,
            storagePath: "qr/products/{$product->slug}.png",
        ));
    }
}
