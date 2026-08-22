<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PauseListingsForProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $productId)
    {
    }

    public function handle(): void
    {
        $variantIds = DB::table('product_variants')
            ->where('product_id', $this->productId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($variantIds))
            return;

        DB::table('seller_listings')
            ->whereIn('product_variant_id', $variantIds)
            ->whereIn('status', ['active', 'pending'])
            ->update(['status' => 'paused', 'updated_at' => now()]);
    }
}
