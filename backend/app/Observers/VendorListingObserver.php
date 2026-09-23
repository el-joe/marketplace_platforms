<?php

namespace App\Observers;

use App\Jobs\RecomputeListingShippingMethodsJob;
use App\Models\Country;
use App\Models\ProductPriceHistory;
use App\Models\VendorListing;
use App\Services\CachedListingResolver;
use App\Services\Customer\BuyBoxRebuildService;
use App\Services\Marketer\MarketerListingAvailabilityService;
use App\Services\Shared\PageCacheService;
use Illuminate\Support\Facades\Auth;

class VendorListingObserver
{
    public function __construct(
        private readonly CachedListingResolver $cachedListingResolver,
        private readonly PageCacheService $pageCache,
        private readonly MarketerListingAvailabilityService $marketerAvailability,
        private readonly BuyBoxRebuildService $buyBoxRebuilder,
    ) {}

    public function created(VendorListing $listing): void
    {
        dispatch(new RecomputeListingShippingMethodsJob(
            [$listing->productVariant->product->category_id]
        ));

        $this->cachedListingResolver->bustVendorListing($listing);
        $this->rebuildBuyBox($listing);
        $this->lockFirstPrice($listing);
    }

    /**
     * Client feature request doc, section 6: the first price a vendor ever
     * sets (even on a draft) is locked in permanently for admin reference,
     * independent of the price-history log below.
     */
    private function lockFirstPrice(VendorListing $listing): void
    {
        $now = now();

        $listing->newQueryWithoutScopes()->whereKey($listing->getKey())->update([
            'first_price' => $listing->price,
            'first_price_locked_at' => $now,
        ]);
        $listing->setRawAttributes(array_merge($listing->getAttributes(), [
            'first_price' => $listing->price,
            'first_price_locked_at' => $now,
        ]), true);

        ProductPriceHistory::create([
            'vendor_listing_id' => $listing->id,
            'price' => $listing->price,
            'recorded_at' => $now,
            'source' => 'initial',
            'recorded_by' => $this->currentActorId(),
        ]);
    }

    /**
     * Section 6: every price change after creation is logged, but
     * first_price/first_price_locked_at are never touched again — not even
     * by admin.
     */
    public function updating(VendorListing $listing): void
    {
        if ($listing->isDirty('price')) {
            ProductPriceHistory::create([
                'vendor_listing_id' => $listing->id,
                'price' => $listing->price,
                'recorded_at' => now(),
                'source' => 'update',
                'recorded_by' => $this->currentActorId(),
            ]);
        }

        // Read-only after creation, even if something upstream tries to set them.
        if ($listing->isDirty('first_price') || $listing->isDirty('first_price_locked_at')) {
            $listing->first_price = $listing->getOriginal('first_price');
            $listing->first_price_locked_at = $listing->getOriginal('first_price_locked_at');
        }
    }

    private function currentActorId(): ?string
    {
        foreach (['admin', 'vendor', 'vendor_api'] as $guard) {
            if ($id = Auth::guard($guard)->id()) {
                return $id;
            }
        }

        return null;
    }

    public function updated(VendorListing $listing): void
    {
        if ($listing->wasChanged('global_system_type')) {
            dispatch(new RecomputeListingShippingMethodsJob(
                [$listing->productVariant->product->category_id]
            ));
        }

        if ($listing->wasChanged(['status', 'price', 'score', 'rating_avg', 'rating_count',
            'primary_shipping_method_id', 'vendor_covers_delivery'])) {
            $this->cachedListingResolver->bustVendorListing($listing);
            $this->pageCache->bustVendorListing($listing);
        }

        // enhancement.md P-15 task 4: keep marketer listings sourced from
        // this vendor listing in sync (status/price), independent of P-14's
        // campaign-level pausing.
        if ($listing->wasChanged(['status', 'price'])) {
            $this->marketerAvailability->syncAllForSource('vendor_listing', $listing->id);
        }

        // enhancement.md P-19 task 2: buy-box winner/aggregates depend on
        // every one of these columns.
        if ($listing->wasChanged(['status', 'price', 'compare_at_price', 'score', 'rating_avg',
            'rating_count', 'fulfillment_model', 'primary_shipping_method_id'])) {
            $this->rebuildBuyBox($listing);
        }
    }

    public function deleted(VendorListing $listing): void
    {
        $this->cachedListingResolver->bustVendorListing($listing);
        $this->pageCache->bustVendorListing($listing);
        $this->rebuildBuyBox($listing);
    }

    private function rebuildBuyBox(VendorListing $listing): void
    {
        $productId = $listing->productVariant?->product_id;
        $country = $productId ? Country::find($listing->country_id) : null;

        if ($productId && $country) {
            $this->buyBoxRebuilder->rebuildProducts([$productId], $country);
        }
    }
}
