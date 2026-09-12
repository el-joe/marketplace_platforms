<?php

namespace App\Services\Customer;

use App\Models\Category;
use App\Models\Setting;

class CodValidationService
{
    /**
     * Validate a cart's items against the admin-configured COD limits.
     * Nawi/platform (admin_listings) products are always exempt. Items in the
     * Super Mall category subtree are checked against a separate limit.
     *
     * @param  array<\App\Models\CartItem>  $cartItems
     * @return array<int, string> Validation error messages; empty when COD is allowed.
     */
    public function validate(array $cartItems): array
    {
        $errors = [];

        $regularTotal = 0;
        $supermallTotal = 0;

        $supermallRange = $this->supermallLftRgtRange();

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

        $globalLimit = (int) Setting::get('cod_global_max_amount', 0);
        if ($globalLimit > 0 && $regularTotal > $globalLimit) {
            $errors[] = __('common.exceptions.checkout.cod_limit_exceeded', ['limit' => $globalLimit]);
        }

        $supermallLimit = (int) Setting::get('cod_supermall_max_amount', 0);
        if ($supermallLimit > 0 && $supermallTotal > $supermallLimit) {
            $errors[] = __('common.exceptions.checkout.cod_supermall_limit_exceeded', ['limit' => $supermallLimit]);
        }

        return $errors;
    }

    /**
     * @return array{0: int, 1: int}|null [lft, rgt] of the configured Super Mall category, or null when unset.
     */
    private function supermallLftRgtRange(): ?array
    {
        $categoryId = Setting::get('cod_supermall_category_id', '');

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
