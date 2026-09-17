<?php

namespace App\Services\Marketer;

use App\Models\AdminListing;
use App\Models\MarketerListing;
use App\Models\VendorListing;

/**
 * enhancement.md P-15 tasks 2-4.
 *
 * Business-rule decision (task 2, "confirm with the owner" — no owner
 * available, so this is the documented default): an INDEPENDENT marketer
 * listing (no invitation) must bind to one concrete, existing, active
 * vendor_listing or admin_listing for the same product variant + country
 * at CREATION time. We auto-pick the cheapest active listing (vendor
 * listings preferred over admin/platform listings, matching the buy-box
 * priority used everywhere else) and store it on
 * source_type/source_listing_id. If none exists, creation is rejected —
 * the listing is never created "for later"; the marketer must add it once
 * a seller is actually selling that variant in that country.
 *
 * This supersedes P-02's CartLineSource::resolveIndependentMarketerFulfilment(),
 * which re-ran this same "any active listing" lookup at checkout time on
 * every cart resolve. That runtime fallback is removed: source_listing_id
 * is now resolved once, explicitly, at listing-creation time, and checkout
 * simply reads it.
 */
class MarketerListingAvailabilityService
{
    public function priceBoundPct(): float
    {
        return (float) config('marketer.listing_price_bound_pct', 20);
    }

    /**
     * @return array{0: int, 1: int} [min_price, max_price], inclusive, BIGINT base-currency.
     */
    public function priceBounds(int $sourcePrice): array
    {
        $pct = $this->priceBoundPct();
        $min = (int) floor($sourcePrice * (1 - $pct / 100));
        $max = (int) ceil($sourcePrice * (1 + $pct / 100));

        return [max($min, 0), $max];
    }

    public function isPriceInBounds(int $price, int $sourcePrice): bool
    {
        [$min, $max] = $this->priceBounds($sourcePrice);

        return $price >= $min && $price <= $max;
    }

    /**
     * Resolve the best active source listing for an independent listing,
     * cheapest first, vendor listings preferred over admin listings.
     *
     * @return array{0: string, 1: VendorListing|AdminListing}|null [source_type, listing]
     */
    public function resolveBestSource(string $productVariantId, string $countryId): ?array
    {
        $vendorListing = VendorListing::query()
            ->where('product_variant_id', $productVariantId)
            ->where('country_id', $countryId)
            ->where('status', 'active')
            ->orderBy('price')
            ->first();

        if ($vendorListing) {
            return ['vendor_listing', $vendorListing];
        }

        $adminListing = AdminListing::query()
            ->where('product_variant_id', $productVariantId)
            ->where('country_id', $countryId)
            ->where('status', 'active')
            ->orderBy('price')
            ->first();

        if ($adminListing) {
            return ['admin_listing', $adminListing];
        }

        return null;
    }

    public function loadSource(MarketerListing $listing): VendorListing|AdminListing|null
    {
        if (! $listing->source_type || ! $listing->source_listing_id) {
            return null;
        }

        return $listing->source_type === 'vendor_listing'
            ? VendorListing::find($listing->source_listing_id)
            : AdminListing::find($listing->source_listing_id);
    }

    /**
     * Re-evaluate one marketer listing against its current source
     * (status/stock/price) and pause/unpause it accordingly. Synchronous —
     * callers are observers/listeners fired within the same request, so
     * storefront reads immediately after see the new state (P-15
     * acceptance criterion).
     */
    public function syncListing(MarketerListing $listing): void
    {
        if ($listing->status === 'archived') {
            return; // never touch an archived listing
        }

        if ($listing->status === 'paused' && $listing->paused_reason === 'manual') {
            return; // the marketer paused it themselves; leave it alone
        }

        $source = $this->loadSource($listing);

        $available = $source !== null
            && ($source->status?->value ?? $source->status) === 'active';

        $priceOk = $source !== null
            && $this->isPriceInBounds((int) $listing->getRawOriginal('price'), (int) $source->getRawOriginal('price'));

        if (! $available || ! $priceOk) {
            if ($listing->status !== 'paused' || $listing->paused_reason !== 'source_unavailable') {
                $listing->update(['status' => 'paused', 'paused_reason' => 'source_unavailable']);
            }

            return;
        }

        // Only auto-unpause a listing WE auto-paused; a manually paused
        // listing stays paused until the marketer reactivates it.
        if ($listing->status === 'paused' && $listing->paused_reason === 'source_unavailable') {
            $listing->update(['status' => 'active', 'paused_reason' => null]);
        }
    }

    /**
     * Re-evaluate every marketer listing bound to one source listing —
     * called from the vendor/admin listing observers and from the
     * ListingStockChanged listener.
     */
    public function syncAllForSource(string $sourceType, string $sourceListingId): void
    {
        MarketerListing::query()
            ->where('source_type', $sourceType)
            ->where('source_listing_id', $sourceListingId)
            ->where('listing_category', 'product')
            ->get()
            ->each(fn (MarketerListing $ml) => $this->syncListing($ml));
    }
}
