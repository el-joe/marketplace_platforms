<?php

namespace App\Services;

use App\Enums\AdminListingStatus;
use App\Enums\VendorListingStatus;
use App\Models\AdminListing;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorListing;

class VariantSlugService
{
    /**
     * Build a variant slug from its is_variant_attribute values.
     * Sorted by attribute.sort_order, attribute_value slugs joined with "-".
     */
    public function buildSlug(ProductVariant $variant): string
    {
        return $variant->variantAttributeValues()
            ->with('attribute')
            ->get()
            ->sortBy(fn (AttributeValue $value) => $value->attribute?->sort_order ?? 0)
            ->map(fn (AttributeValue $value) => $value->slug)
            ->filter()
            ->implode('-');
    }

    /**
     * Given a product and a variant slug, find the matching ProductVariant.
     * The slug must match exactly.
     */
    public function resolveVariant(Product $product, string $variantSlug): ?ProductVariant
    {
        return $product->variants()
            ->where('slug', $variantSlug)
            ->first();
    }

    /**
     * Given a product, the CURRENTLY selected attribute_value IDs (keyed by attribute_id),
     * and ONE changed attribute (attribute_id => new attribute_value_id),
     * find the ProductVariant that matches ALL the attribute values after the change.
     */
    public function resolveVariantAfterAttributeChange(
        Product $product,
        array $currentAttributeValueIds,
        string $changedAttributeId,
        string $newAttributeValueId
    ): ?ProductVariant {
        $targetAttributeValueIds = $currentAttributeValueIds;
        $targetAttributeValueIds[$changedAttributeId] = $newAttributeValueId;

        $variants = $product->variants()
            ->where('is_active', true)
            ->with('variantAttributeValues')
            ->get();

        foreach ($variants as $variant) {
            $variantAttributeValueIds = $variant->variantAttributeValues
                ->pluck('id', 'attribute_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $normalizedTarget = array_map('strval', $targetAttributeValueIds);

            if ($variantAttributeValueIds == $normalizedTarget) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Given a ProductVariant and country_id, find the best active VendorListing.
     * "Best" = lowest price among active listings with stock > 0.
     * Falls back to lowest price even if out_of_stock.
     */
    public function bestVendorListing(ProductVariant $variant, string $countryId): ?VendorListing
    {
        $activeListings = $variant->vendorListings()
            ->where('country_id', $countryId)
            ->where('status', VendorListingStatus::Active->value)
            ->with('warehouseInventories')
            ->orderBy('price')
            ->get();

        $inStock = $activeListings->first(
            fn (VendorListing $listing) => $listing->warehouseInventories
                ->sum(fn ($inventory) => $inventory->quantity_on_hand - $inventory->quantity_reserved) > 0
        );

        return $inStock ?? $activeListings->first();
    }

    /**
     * Same as above but for AdminListing (for nawy_now context).
     */
    public function bestAdminListing(ProductVariant $variant, string $countryId): ?AdminListing
    {
        $activeListings = $variant->adminListings()
            ->where('country_id', $countryId)
            ->where('status', AdminListingStatus::Active->value)
            ->with('warehouseInventories')
            ->orderBy('price')
            ->get();

        $inStock = $activeListings->first(
            fn (AdminListing $listing) => $listing->warehouseInventories
                ->sum(fn ($inventory) => $inventory->quantity_on_hand - $inventory->quantity_reserved) > 0
        );

        return $inStock ?? $activeListings->first();
    }

    /**
     * Given a product and a listing_id (vendor or admin), resolve the variant
     * and verify the listing belongs to it.
     *
     * @return array{variant: ProductVariant, listing: VendorListing|AdminListing, type: string}|null
     */
    public function resolveByListingId(Product $product, string $listingId): ?array
    {
        $vendorListing = VendorListing::where('id', $listingId)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('productVariant', fn ($query) => $query->where('product_id', $product->id))
            ->with('productVariant')
            ->first();

        if ($vendorListing) {
            return [
                'variant' => $vendorListing->productVariant,
                'listing' => $vendorListing,
                'type' => 'vendor',
            ];
        }

        $adminListing = AdminListing::where('id', $listingId)
            ->where('status', AdminListingStatus::Active->value)
            ->whereHas('productVariant', fn ($query) => $query->where('product_id', $product->id))
            ->with('productVariant')
            ->first();

        if ($adminListing) {
            return [
                'variant' => $adminListing->productVariant,
                'listing' => $adminListing,
                'type' => 'admin',
            ];
        }

        return null;
    }
}
