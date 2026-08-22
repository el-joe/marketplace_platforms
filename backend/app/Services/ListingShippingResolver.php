<?php

namespace App\Services;

use App\Enums\GlobalSystemType;
use App\Models\AdminListing;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\VendorListing;
use Illuminate\Support\Collection;

class ListingShippingResolver
{
    /**
     * Resolve the shipping methods available for a not-yet-created listing,
     * given only its product variant and intended fulfillment model.
     */
    public function resolveForVariant(string $variantId, string $fulfillmentModel): Collection
    {
        $variant = ProductVariant::with('product.category')->find($variantId);
        if (!$variant || !$variant->product?->category) return collect();

        $category = $this->resolveCategory($variant->product->category);
        if (!$category) return collect();

        $fbpField = $fulfillmentModel === 'fbn' ? 'is_available_for_express_fbn' : 'is_available_for_merchant_fbp';

        return $category->shippingMethods()
            ->where("category_shipping_methods.{$fbpField}", true)
            ->orderBy('shipping_methods.display_priority')
            ->get();
    }

    public function resolveForListing(VendorListing|AdminListing $listing): Collection
    {
        $category = $this->resolveCategory(
            $listing->productVariant->product->category
        );

        if (!$category) return collect();

        // $isFBN = $listing->global_system_type === 'express_fbn';
        // $fbpField = $isFBN ? 'is_available_for_express_fbn' : 'is_available_for_merchant_fbp';

        return $category->shippingMethods()
            // ->where("category_shipping_methods.{$fbpField}", true)
            ->orderBy('shipping_methods.display_priority')
            ->get();
    }

    public function resolvePrimary(VendorListing $listing): ?ShippingMethod
    {
        $category = $this->resolveCategory(
            $listing->productVariant->product->category
        );

        if (!$category) return null;

        $isFBN = $listing->global_system_type === GlobalSystemType::ExpressFbn;
        $fbpField = $isFBN ? 'is_available_for_express_fbn' : 'is_available_for_merchant_fbp';

        return $category->shippingMethods()
            ->where('category_shipping_methods.is_default', true)
            ->where("category_shipping_methods.{$fbpField}", true)
            ->first();
    }

    public function cacheOnListing(VendorListing $listing): void
    {
        $primary = $this->resolvePrimary($listing);
        $listing->updateQuietly([
            'primary_shipping_method_id' => $primary?->id,
        ]);
    }

    private function resolveCategory(Category $category): ?Category
    {
        $node = $category;
        while ($node) {
            if ($node->shippingMethods()->exists()) return $node;
            $node = $node->parent;
        }
        return null;
    }
}
