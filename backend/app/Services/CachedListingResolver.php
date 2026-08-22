<?php

namespace App\Services;

use App\Models\AdminListing;
use App\Models\VendorListing;
use Illuminate\Support\Facades\Cache;

class CachedListingResolver
{
    private const TTL_SECONDS = 120;

    public function __construct(
        private readonly VariantResolutionService $variantResolutionService,
    ) {
    }

    public function bestVendorListingId(string $variantId, string $countryId): ?string
    {
        return Cache::remember(
            $this->vendorCacheKey($variantId, $countryId),
            self::TTL_SECONDS,
            fn () => $this->variantResolutionService->bestVendorListing($variantId, $countryId)?->id
        );
    }

    public function bestAdminListingId(string $variantId, string $countryId): ?string
    {
        return Cache::remember(
            $this->adminCacheKey($variantId, $countryId),
            self::TTL_SECONDS,
            fn () => $this->variantResolutionService->bestAdminListing($variantId, $countryId)?->id
        );
    }

    /**
     * Called from VendorListingObserver on saved/deleted. The cache key is keyed by
     * country, which we cannot know for a bulk-changed listing beyond the one that
     * triggered the save, so we only forget the entry for that listing's own country.
     * Cross-country staleness self-heals within the TTL.
     */
    public function bustVendorListing(VendorListing $listing): void
    {
        Cache::forget($this->vendorCacheKey($listing->product_variant_id, $listing->country_id));
    }

    public function bustAdminListing(AdminListing $listing): void
    {
        Cache::forget($this->adminCacheKey($listing->product_variant_id, $listing->country_id));
    }

    private function vendorCacheKey(string $variantId, string $countryId): string
    {
        return "best_listing.vendor.{$variantId}.{$countryId}";
    }

    private function adminCacheKey(string $variantId, string $countryId): string
    {
        return "best_listing.admin.{$variantId}.{$countryId}";
    }
}
