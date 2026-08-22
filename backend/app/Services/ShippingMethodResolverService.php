<?php

namespace App\Services;

use App\Models\AdminListing;
use App\Models\CountryShippingSetting;
use App\Models\ShippingMethod;
use App\Models\VendorListing;
use Illuminate\Support\Collection;

/**
 * Resolves which shipping methods are available for a listing, applying
 * category + country + fulfillment_model filters.
 */
class ShippingMethodResolverService
{
    public function getAvailableForListing(string $listingId, string $listingType, string $countryId): Collection
    {
        $listing = $this->loadListing($listingId, $listingType);

        if (!$listing || !$listing->productVariant?->product?->category) {
            return collect();
        }

        $categoryId = $listing->productVariant->product->category_id;
        $fbpField = $this->resolveFbpField($listing, $listingType);

        $methods = ShippingMethod::query()
            ->select('shipping_methods.*', 'category_shipping_methods.is_default as pivot_is_default')
            ->join('category_shipping_methods', 'category_shipping_methods.shipping_method_id', '=', 'shipping_methods.id')
            ->join('country_shipping_settings', function ($join) use ($countryId) {
                $join->on('country_shipping_settings.shipping_method_id', '=', 'shipping_methods.id')
                    ->where('country_shipping_settings.country_id', $countryId)
                    ->where('country_shipping_settings.is_active', true);
            })
            ->where('category_shipping_methods.category_id', $categoryId)
            ->where('shipping_methods.is_active', true)
            ->where("category_shipping_methods.{$fbpField}", true)
            ->orderBy('shipping_methods.display_priority')
            ->get();

        // VERIFY: business rule for filtering by requires_special_vehicle / requires_refrigeration
        // against marketplace_shipping_rules is not yet defined (no per-method vehicle/refrigeration
        // capability column exists on shipping_methods). Confirm intended filtering before enforcing it.
        $shippingRule = $listing->marketplaceShippingRule ?? null;
        if ($shippingRule && ($shippingRule->requires_special_vehicle || $shippingRule->requires_refrigeration)) {
            // VERIFY: no-op until the capability columns/logic are confirmed.
        }

        $defaultMethodId = $listing->primary_shipping_method_id
            ?? $methods->firstWhere('pivot_is_default', true)?->id;

        return $methods->map(function (ShippingMethod $method) use ($defaultMethodId) {
            $method->is_default = $method->id === $defaultMethodId;
            unset($method->pivot_is_default);
            return $method;
        });
    }

    public function getDefaultForListing(string $listingId, string $listingType, string $countryId): ?ShippingMethod
    {
        $methods = $this->getAvailableForListing($listingId, $listingType, $countryId);

        if ($methods->isEmpty()) {
            return null;
        }

        return $methods->firstWhere('is_default', true) ?? $methods->first();
    }

    public function validateMethodForListing(string $methodId, string $listingId, string $listingType, string $countryId): bool
    {
        return $this->getAvailableForListing($listingId, $listingType, $countryId)
            ->contains('id', $methodId);
    }

    public function buildMethodResponse(ShippingMethod $method, string $countryId, bool $isDefault): array
    {
        $countrySetting = CountryShippingSetting::query()
            ->where('shipping_method_id', $method->id)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->first();

        [$textEn, $textAr] = $this->buildEstimatedDeliveryText($method);

        return [
            'id' => $method->id,
            'name' => $method->name,
            'code' => $method->code,
            'badge_label_en' => $method->badge_label_en,
            'badge_label_ar' => $method->badge_label_ar,
            'badge_color_hex' => $method->badge_color_hex,
            'badge_text_color_hex' => $method->badge_text_color_hex,
            'delivery_label_en' => $method->delivery_label_en,
            'delivery_label_ar' => $method->delivery_label_ar,
            'is_express_type' => (bool) $method->is_express_type,
            'is_default' => $isDefault,
            'min_delivery_days' => $method->min_delivery_days,
            'max_delivery_days' => $method->max_delivery_days,
            'estimated_delivery_text_en' => $textEn,
            'estimated_delivery_text_ar' => $textAr,
            'show_estimated_price' => (bool) $method->show_estimated_price,
            'free_shipping_threshold' => $countrySetting?->free_shipping_threshold,
            'is_free_for_order' => null,
        ];
    }

    private function buildEstimatedDeliveryText(ShippingMethod $method): array
    {
        $min = $method->min_delivery_days;
        $max = $method->max_delivery_days;

        if ($min === null && $max === null) {
            return [$method->delivery_label_en, $method->delivery_label_ar];
        }

        if ($min === $max) {
            return [
                "Delivered in {$min} day(s)",
                "يصل خلال {$min} يوم",
            ];
        }

        return [
            "Delivered in {$min}–{$max} days",
            "يصل خلال {$min}–{$max} يوم",
        ];
    }

    private function loadListing(string $listingId, string $listingType): VendorListing|AdminListing|null
    {
        $with = ['productVariant.product.category', 'country', 'marketplaceShippingRule'];

        if ($listingType === 'vendor_listing') {
            return VendorListing::query()->with($with)->find($listingId);
        }

        return AdminListing::query()->with($with)->find($listingId);
    }

    private function resolveFbpField(VendorListing|AdminListing $listing, string $listingType): string
    {
        $fulfillment = $listing->fulfillment_model;

        $fulfillment = $fulfillment instanceof \BackedEnum ? $fulfillment->value : $fulfillment;

        return match ($fulfillment) {
            'fbn', 'express' => 'is_available_for_express_fbn',
            default => 'is_available_for_merchant_fbp',
        };
    }
}
