<?php

namespace App\Services\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorGlobalStatus;
use App\Enums\VendorListingStatus;
use App\Models\AdminListing;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorListing;

class ListingIdentifierService
{
    private const EAGER_LOADS = [
        'productVariant.product.images',
        'productVariant.product.category',
        'productVariant.product.brand',
        'productVariant.product.highlights',
        'productVariant.product.specifications',
        'productVariant.variantAttributes.attribute',
        'productVariant.variantAttributes.attributeValue',
        'vendor:id,store_name,store_rating_avg,store_rating_count',
        'primaryShippingMethod',
    ];

    private const ADMIN_EAGER_LOADS = [
        'productVariant.product.images',
        'productVariant.product.category',
        'productVariant.product.brand',
        'productVariant.product.highlights',
        'productVariant.product.specifications',
        'productVariant.variantAttributes.attribute',
        'productVariant.variantAttributes.attributeValue',
        'primaryShippingMethod',
    ];

    /**
     * Resolve a VendorListing from any identifier type.
     * This is the SINGLE entry point for all listing resolution throughout the API.
     */
    public function resolve(
        string $identifier,
        string $type,
        Country $country,
        bool $activeOnly = true,
    ): ?VendorListing {
        return match ($type) {
            'listing_id' => $this->resolveByListingId($identifier, $country, $activeOnly),
            'sku' => $this->resolveBySku($identifier, $country, $activeOnly),
            'vendor_sku' => $this->resolveByVendorSku($identifier, $country, $activeOnly),
            'product_slug' => $this->resolveByProductSlug($identifier, $country),
            default => null,
        };
    }

    private function resolveByListingId(string $identifier, Country $country, bool $activeOnly): ?VendorListing
    {
        return VendorListing::where('id', $identifier)
            ->where('country_id', $country->id)
            ->when($activeOnly, fn ($q) => $q->where('status', VendorListingStatus::Active->value))
            ->with(self::EAGER_LOADS)
            ->first();
    }

    private function resolveBySku(string $identifier, Country $country, bool $activeOnly): ?VendorListing
    {
        return VendorListing::whereHas('productVariant', fn ($q) => $q->where('sku', $identifier))
            ->where('country_id', $country->id)
            ->when($activeOnly, fn ($q) => $q->where('status', VendorListingStatus::Active->value))
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->with(self::EAGER_LOADS)
            ->first();
    }

    private function resolveByVendorSku(string $identifier, Country $country, bool $activeOnly): ?VendorListing
    {
        return VendorListing::where('vendor_sku', $identifier)
            ->where('country_id', $country->id)
            ->when($activeOnly, fn ($q) => $q->where('status', VendorListingStatus::Active->value))
            ->with(self::EAGER_LOADS)
            ->first();
    }

    private function resolveByProductSlug(string $identifier, Country $country): ?VendorListing
    {
        $product = Product::where('slug', $identifier)->where('status', ProductStatus::Active->value)->first();

        if (!$product) {
            return null;
        }

        return VendorListing::whereHas('productVariant', fn ($q) => $q->where('product_id', $product->id))
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->with(self::EAGER_LOADS)
            ->first();
    }

    /**
     * Resolve an AdminListing (admin listing mode) from any identifier type:
     * admin listing UUID, or product_slug (via product_variant -> product).
     * Only active listings are returned.
     */
    public function resolveAdminListing(string $identifier, string $countryId): ?AdminListing
    {
        $base = fn () => AdminListing::where('country_id', $countryId)
            ->where('status', AdminListingStatus::Active->value);

        $listing = $base()->where('id', $identifier)->with(self::ADMIN_EAGER_LOADS)->first();

        if ($listing) {
            return $listing;
        }

        $product = Product::where('slug', $identifier)->where('status', ProductStatus::Active->value)->first();

        if (!$product) {
            return null;
        }

        return $base()
            ->whereHas('productVariant', fn ($q) => $q->where('product_id', $product->id))
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->with(self::ADMIN_EAGER_LOADS)
            ->first();
    }

    /**
     * Detect identifier type automatically from the identifier string.
     * Use this when the caller doesn't know the type.
     */
    public function detectType(string $identifier): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return 'listing_id';
        }

        if (preg_match('/^[a-z][a-z0-9-]{15,}$/', $identifier) && substr_count($identifier, '-') >= 3) {
            return 'product_slug';
        }

        if (ProductVariant::where('sku', $identifier)->exists()) {
            return 'sku';
        }

        return 'vendor_sku';
    }

    /**
     * Get all active listings for the same product that the given listing belongs to.
     * Used on listing detail pages to show "other sellers" and "other variants".
     *
     * @return array{
     *   same_variant: \Illuminate\Support\Collection,
     *   other_variants: \Illuminate\Support\Collection,
     * }
     */
    public function getSiblings(VendorListing $listing, Country $country): array
    {
        $variantId = $listing->product_variant_id;
        $productId = $listing->productVariant->product_id;

        $sameVariant = VendorListing::where('product_variant_id', $variantId)
            ->where('id', '!=', $listing->id)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('vendor', fn ($q) => $q->where('global_status', VendorGlobalStatus::Active->value))
            ->with(['vendor:id,store_name,store_rating_avg', 'primaryShippingMethod'])
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->limit(10)
            ->get();

        $otherVariants = ProductVariant::where('product_id', $productId)
            ->where('id', '!=', $variantId)
            ->where('is_active', 1)
            ->get()
            ->map(fn (ProductVariant $variant) => VendorListing::where('product_variant_id', $variant->id)
                ->where('country_id', $country->id)
                ->where('status', VendorListingStatus::Active->value)
                ->with([
                    'vendor:id,store_name',
                    'productVariant.variantAttributes.attribute',
                    'productVariant.variantAttributes.attributeValue',
                    'primaryShippingMethod',
                ])
                ->orderByRaw('score IS NULL, score DESC')
                ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
                ->orderByDesc('rating_count')
                ->orderBy('price')
                ->first())
            ->filter()
            ->values();

        return ['same_variant' => $sameVariant, 'other_variants' => $otherVariants];
    }

    /**
     * Build the canonical listing URL identifier string.
     * Format: "{product_variant_sku}--{listing_id_short}"
     */
    public function buildListingRef(VendorListing|AdminListing $listing): string
    {
        $sku = $listing->productVariant->sku;
        $shortId = substr(str_replace('-', '', $listing->id), 0, 8);

        return $sku.'--'.$shortId;
    }

    /**
     * Parse a listing_ref back into listing_id + sku components.
     * Returns null if format is invalid.
     */
    public function parseListingRef(string $listingRef): ?array
    {
        if (!str_contains($listingRef, '--')) {
            return null;
        }

        $parts = explode('--', $listingRef, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return ['product_variant_id' => $parts[0], 'listing_id_prefix' => $parts[1]];
    }
}
