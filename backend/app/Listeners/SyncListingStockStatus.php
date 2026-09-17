<?php

namespace App\Listeners;

use App\Events\ListingStockChanged;
use App\Models\AdminListing;
use App\Models\VendorListing;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-13 task 4: switch a listing between 'active' and
 * 'out_of_stock' as its available stock crosses zero, without ever
 * touching 'paused', 'draft', 'rejected' or 'archived'.
 */
class SyncListingStockStatus
{
    public function handle(ListingStockChanged $event): void
    {
        if ($event->vendorListingId) {
            $this->sync(VendorListing::class, $event->vendorListingId);
        }

        if ($event->adminListingId) {
            $this->sync(AdminListing::class, $event->adminListingId);
        }
    }

    private function sync(string $modelClass, string $listingId): void
    {
        $listing = $modelClass::find($listingId);

        if (! $listing || ! in_array($listing->status?->value ?? $listing->status, ['active', 'out_of_stock'], true)) {
            // Never touch paused/draft/rejected/archived listings.
            return;
        }

        $column = $modelClass === VendorListing::class ? 'vendor_listing_id' : 'admin_listing_id';
        $available = (int) DB::table('warehouse_inventories')
            ->where($column, $listingId)
            ->sum(DB::raw('quantity_on_hand - quantity_reserved'));

        $shouldBeOutOfStock = $available <= 0;
        $isOutOfStock = ($listing->status?->value ?? $listing->status) === 'out_of_stock';

        if ($shouldBeOutOfStock && ! $isOutOfStock) {
            $listing->update(['status' => 'out_of_stock']);
        } elseif (! $shouldBeOutOfStock && $isOutOfStock) {
            $listing->update(['status' => 'active']);
        }
    }
}
