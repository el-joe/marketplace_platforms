<?php

namespace App\Services\Customer;

use App\Models\Country;
use App\Models\Product;

class ProductViewService
{
    public function logView(
        Product $product,
        Country $country,
        ?string $customerId,
        string $sessionId,
        string $source = 'direct',
        ?string $referrerUrl = null,
    ): void {
        $product->increment('view_count');

        dispatch(new \App\Jobs\ProductViewLogJob(
            productId: $product->id,
            customerId: $customerId,
            sessionId: $sessionId,
            source: $source,
            referrerUrl: $referrerUrl,
        ))->afterResponse();
    }
}
