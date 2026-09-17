<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * enhancement.md P-13 task 4: fired by InventoryService after any stock
 * mutation so a listener can resync listing.status (active <-> out_of_stock)
 * and invalidate caches, without every one of the 7+ mutation call sites
 * having to know about listing status rules.
 */
class ListingStockChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ?string $vendorListingId,
        public readonly ?string $adminListingId,
    ) {}
}
