<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\Media\ListingImageResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps ListingImageResolver's per-variant cache correct whenever a
 * ProductImage row is written or removed.
 *
 * A variant-level row only affects its own variant. A product-level
 * (fallback) row potentially affects every variant of that product that
 * has no images of its own, so every sibling variant id is invalidated too
 * — cheap, since it only runs on writes, not reads.
 */
class ProductImageObserver
{
    public function saved(ProductImage $image): void
    {
        $this->invalidate($image);
    }

    public function deleted(ProductImage $image): void
    {
        $this->invalidate($image);
    }

    private function invalidate(ProductImage $image): void
    {
        if ($image->product_variant_id) {
            Cache::forget(ListingImageResolver::cacheKey($image->product_variant_id));

            return;
        }

        if ($image->product_id) {
            $variantIds = DB::table('product_variants')
                ->where('product_id', $image->product_id)
                ->pluck('id');

            foreach ($variantIds as $variantId) {
                Cache::forget(ListingImageResolver::cacheKey($variantId));
            }
        }
    }
}
