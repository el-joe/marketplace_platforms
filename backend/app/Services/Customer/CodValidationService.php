<?php

namespace App\Services\Customer;

use App\Models\Category;
use App\Models\Country;
use App\Services\Checkout\CartLineSource;

class CodValidationService
{
    /**
     * Validate a cart's items against the admin-configured COD limits.
     * Nawi/platform (admin_listings) products are always exempt. Items in the
     * Super Mall category subtree are checked against a separate limit.
     *
     * docs/plans/international_product_shipping.md Phase 3 / design decision
     * #4 (adopted Q1 answer): COD is prepaid-only for international lines —
     * cross-border COD collection is operationally unreliable. When
     * $destinationCountryId is given, any cart line whose fulfilment
     * listing's country differs from it rejects COD outright (a hard
     * rejection, not a limit check like the two below).
     *
     * @param  array<\App\Models\CartItem>  $cartItems
     * @return array<int, string> Validation error messages; empty when COD is allowed.
     */
    public function validate(array $cartItems, string $destinationCountryId): array
    {
        $errors = [];

        foreach ($cartItems as $item) {
            $source = CartLineSource::resolve($item);
            if ($source !== null && $source->isInternational($destinationCountryId)) {
                $errors[] = __('common.exceptions.checkout.cod_international_not_allowed');
                break;
            }
        }

        $country = Country::find($destinationCountryId);

        $regularTotal = 0;
        $supermallTotal = 0;

        $supermallRange = $this->supermallLftRgtRange($country);

        foreach ($cartItems as $item) {
            // Nawi/platform (and by extension global) products are exempt from COD limits.
            if ($item->adminListing !== null) {
                continue;
            }

            $lineTotal = (int) $item->unit_price * (int) $item->quantity;

            if ($supermallRange !== null && $this->itemInSupermall($item, $supermallRange)) {
                $supermallTotal += $lineTotal;
            } else {
                $regularTotal += $lineTotal;
            }
        }

        $globalLimit = (int) ($country?->cod_max_amount ?? 0);
        if ($globalLimit > 0 && $regularTotal > $globalLimit) {
            $errors[] = __('common.exceptions.checkout.cod_limit_exceeded', ['limit' => $globalLimit]);
        }

        $supermallLimit = (int) ($country?->cod_supermall_max_amount ?? 0);
        if ($supermallLimit > 0 && $supermallTotal > $supermallLimit) {
            $errors[] = __('common.exceptions.checkout.cod_supermall_limit_exceeded', ['limit' => $supermallLimit]);
        }

        return $errors;
    }

    /**
     * @return array{0: int, 1: int}|null [lft, rgt] of the configured Super Mall category, or null when unset.
     */
    private function supermallLftRgtRange(?Country $country): ?array
    {
        $categoryId = $country?->cod_supermall_category_id;

        if (empty($categoryId)) {
            return null;
        }

        $category = Category::query()->select('lft', 'rgt')->find($categoryId);

        if (!$category || $category->lft === null || $category->rgt === null) {
            return null;
        }

        return [$category->lft, $category->rgt];
    }

    private function itemInSupermall(\App\Models\CartItem $item, array $range): bool
    {
        $categoryId = $item->vendorListing?->productVariant?->product?->category_id;

        if ($categoryId === null) {
            return false;
        }

        $category = Category::query()->select('lft', 'rgt')->find($categoryId);

        if (!$category || $category->lft === null || $category->rgt === null) {
            return false;
        }

        return $category->lft >= $range[0] && $category->rgt <= $range[1];
    }
}
