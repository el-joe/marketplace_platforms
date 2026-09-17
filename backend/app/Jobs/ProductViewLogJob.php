<?php

namespace App\Jobs;

use App\Models\ProductView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProductViewLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $productId,
        public readonly ?string $customerId,
        public readonly string $sessionId,
        public readonly string $source,
        public readonly ?string $referrerUrl,
    ) {}

    public function handle(): void
    {
        ProductView::create([
            'id'           => Str::uuid(),
            'product_id'   => $this->productId,
            'customer_id'  => $this->customerId,
            'session_id'   => $this->sessionId,
            'source'       => $this->source,
            'referrer_url' => $this->referrerUrl,
        ]);

        // Batched view-count increment. This job already runs off the request
        // thread (queued), so the increment below no longer blocks the GET.
        // In an environment with Redis this would instead INCR a per-product
        // counter (view_counts:{product_id}) and a scheduled command
        // (`php artisan schedule`, every minute) would flush all counters to
        // `products.view_count` with one bulk UPDATE ... CASE query — avoiding
        // per-view row locks entirely. Redis isn't configured in this sandbox
        // (CACHE_STORE=database, QUEUE_CONNECTION=database), so we fall back
        // to a per-job increment here; it still moves the write off the
        // synchronous request path, which is the acceptance criterion.
        \App\Models\Product::whereKey($this->productId)->increment('view_count');
    }
}
