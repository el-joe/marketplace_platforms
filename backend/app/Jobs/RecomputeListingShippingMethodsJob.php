<?php

namespace App\Jobs;

use App\Models\VendorListing;
use App\Services\ListingShippingResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecomputeListingShippingMethodsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly array $categoryIds) {}

    public function handle(ListingShippingResolver $resolver): void
    {
        $categoryIds = $this->categoryIds;

        VendorListing::whereIn('product_variant_id', function ($q) use ($categoryIds) {
            $q->select('product_variants.id')
                ->from('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('products.category_id', $categoryIds);
        })
            ->with('productVariant.product.category.parent')
            ->chunk(200, function ($listings) use ($resolver) {
                foreach ($listings as $listing) {
                    $resolver->cacheOnListing($listing);
                }
            });
    }
}
