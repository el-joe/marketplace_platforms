<?php

namespace App\Listeners;

use App\Events\ListingStockChanged;
use App\Models\AdminListing;
use App\Models\Country;
use App\Models\VendorListing;
use App\Services\Customer\BuyBoxRebuildService;

/**
 * enhancement.md P-19 task 2: total_stock in product_country_buybox must
 * follow every warehouse mutation, not just the ones that flip a listing's
 * status across zero (see SyncListingStockStatus, which only writes when
 * status changes). InventoryService fires ListingStockChanged after every
 * stock mutation (enhancement.md P-13 task 4), so this is the single place
 * that keeps the read model's stock figure correct regardless of whether
 * the listing's own status changed.
 */
class RebuildBuyBoxOnStockChange
{
    public function __construct(private readonly BuyBoxRebuildService $rebuilder)
    {
    }

    public function handle(ListingStockChanged $event): void
    {
        if ($event->vendorListingId) {
            $this->rebuild(VendorListing::find($event->vendorListingId));
        }

        if ($event->adminListingId) {
            $this->rebuild(AdminListing::find($event->adminListingId));
        }
    }

    private function rebuild(VendorListing|AdminListing|null $listing): void
    {
        if (! $listing) {
            return;
        }

        $productId = $listing->productVariant?->product_id;
        if (! $productId) {
            return;
        }

        $country = Country::find($listing->country_id);
        if (! $country) {
            return;
        }

        $this->rebuilder->rebuildProducts([$productId], $country);
    }
}
