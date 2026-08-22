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
    }
}
