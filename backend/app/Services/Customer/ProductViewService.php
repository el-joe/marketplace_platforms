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
        // No synchronous write here: the view_count increment and the
        // product_views row are both handled by the queued ProductViewLogJob
        // so a GET on the PDP is read-only.
        //
        // Plain dispatch() (not ->afterResponse()) is deliberate: afterResponse()
        // always runs the job inline via dispatchSync() once the response is
        // flushed — it never actually touches the queue, even for a
        // ShouldQueue job — which still leaves the write in the request's own
        // PHP-FPM worker. A plain dispatch() honors QUEUE_CONNECTION (database
        // in this app) and pushes a `jobs` row for a queue worker to process,
        // fully off the request path.
        dispatch(new \App\Jobs\ProductViewLogJob(
            productId: $product->id,
            customerId: $customerId,
            sessionId: $sessionId,
            source: $source,
            referrerUrl: $referrerUrl,
        ));
    }
}
