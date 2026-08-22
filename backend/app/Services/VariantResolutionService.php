<?php

namespace App\Services;

use App\Enums\AdminListingStatus;
use App\Enums\VendorListingStatus;
use App\Models\AdminListing;
use App\Models\ProductVariant;
use App\Models\VendorListing;

class VariantResolutionService
{
    public function resolveVariantAfterChange(
        string $productId,
        string $currentVariantId,
        string $changedAttributeId,
        string $newAttributeValueId
    ): ?ProductVariant {
        $targetMap = $this->getVariantAttributeMap($currentVariantId);
        $targetMap[$changedAttributeId] = $newAttributeValueId;

        $variants = ProductVariant::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->get();

        foreach ($variants as $variant) {
            if ($this->getVariantAttributeMap($variant->id) === $targetMap) {
                return $variant;
            }
        }

        return null;
    }

    public function bestVendorListing(string $variantId, string $countryId): ?VendorListing
    {
        return VendorListing::query()
            ->where('product_variant_id', $variantId)
            ->where('country_id', $countryId)
            ->whereIn('status', [VendorListingStatus::Active, VendorListingStatus::OutOfStock])
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [VendorListingStatus::Active->value])
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderBy('rating_count', 'desc')
            ->orderBy('price', 'asc')
            ->first();
    }

    public function bestAdminListing(string $variantId, string $countryId): ?AdminListing
    {
        return AdminListing::query()
            ->where('product_variant_id', $variantId)
            ->where('country_id', $countryId)
            ->where('status', AdminListingStatus::Active)
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderBy('rating_count', 'desc')
            ->orderBy('price', 'asc')
            ->first();
    }

    public function getVariantAttributeMap(string $variantId): array
    {
        return ProductVariant::query()
            ->findOrFail($variantId)
            ->variantAttributeValues()
            ->get()
            ->pluck('id', 'attribute_id')
            ->toArray();
    }
}
