<?php

namespace App\Services\Checkout;

use App\Models\AdminListing;
use App\Models\CartItem;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;

/**
 * Normalizes any cart item — vendor listing, admin (platform) listing,
 * campaign marketer listing, or independent marketer listing — into one
 * shape the checkout transaction can use without ever touching
 * `$item->vendorListing` directly (enhancement.md P-02).
 *
 * - `sellable`: the listing the customer actually bought (vendor, admin or
 *   marketer listing). This is what gets recorded on the order item.
 * - `fulfilmentListing`: the listing that owns the warehouse stock — the
 *   vendor or admin listing. For a marketer listing this is the campaign's
 *   source listing (or, for an independent marketer listing with no
 *   campaign, the best active listing found for the same product variant —
 *   see resolveIndependentMarketerFulfilment()).
 * - `sellerParty`: the vendor's id, or the literal string 'platform' for
 *   admin-listing / platform-sourced fulfilment.
 */
class CartLineSource
{
    /**
     * @param  Collection<int, WarehouseInventory>  $warehouseInventories
     */
    private function __construct(
        public readonly CartItem $cartItem,
        public readonly VendorListing|AdminListing|\App\Models\MarketerListing $sellable,
        public readonly VendorListing|AdminListing $fulfilmentListing,
        public readonly string $sellerParty,
        public readonly int $price,
        public readonly int $quantity,
        public readonly string $fulfillmentModel,
        public readonly Collection $warehouseInventories,
        public readonly ?string $vendorListingIdForOrderItem,
        public readonly ?string $adminListingIdForOrderItem,
        public readonly ?string $marketerListingIdForOrderItem,
    ) {}

    public static function resolve(CartItem $item): ?self
    {
        if ($item->vendor_listing_id !== null) {
            $listing = $item->vendorListing;
            if (! $listing) {
                return null;
            }

            return new self(
                cartItem: $item,
                sellable: $listing,
                fulfilmentListing: $listing,
                sellerParty: $listing->vendor_id,
                price: (int) $item->unit_price,
                quantity: (int) $item->quantity,
                fulfillmentModel: (string) ($listing->fulfillment_model ?? 'fbm'),
                warehouseInventories: $listing->relationLoaded('warehouseInventories')
                    ? $listing->warehouseInventories
                    : $listing->warehouseInventories()->get(),
                vendorListingIdForOrderItem: $listing->id,
                adminListingIdForOrderItem: null,
                marketerListingIdForOrderItem: null,
            );
        }

        if ($item->admin_listing_id !== null) {
            $listing = $item->adminListing;
            if (! $listing) {
                return null;
            }

            return new self(
                cartItem: $item,
                sellable: $listing,
                fulfilmentListing: $listing,
                sellerParty: 'platform',
                price: (int) $item->unit_price,
                quantity: (int) $item->quantity,
                // Admin (platform) listings have no fulfillment_model column —
                // platform stock is always fulfilled the same way FBN is.
                fulfillmentModel: 'fbn',
                warehouseInventories: $listing->relationLoaded('warehouseInventories')
                    ? $listing->warehouseInventories
                    : $listing->warehouseInventories()->get(),
                vendorListingIdForOrderItem: null,
                adminListingIdForOrderItem: $listing->id,
                marketerListingIdForOrderItem: null,
            );
        }

        if ($item->marketer_listing_id !== null) {
            $marketerListing = $item->marketerListing;
            if (! $marketerListing) {
                return null;
            }

            $campaign = $marketerListing->invitation?->campaign;
            $sourceVendorListing = $campaign?->vendor_listing_id ? $campaign->vendorListing : null;
            $sourceAdminListing = $campaign?->admin_listing_id ? $campaign->adminListing : null;

            if (! $sourceVendorListing && ! $sourceAdminListing) {
                // Independent marketer listing (no campaign at all) — resolve
                // the best active listing for the same product variant to
                // fulfil from, since a marketer listing never carries its
                // own warehouse stock.
                [$sourceVendorListing, $sourceAdminListing] = self::resolveIndependentMarketerFulfilment($marketerListing);
            }

            if ($sourceVendorListing) {
                return new self(
                    cartItem: $item,
                    sellable: $marketerListing,
                    fulfilmentListing: $sourceVendorListing,
                    sellerParty: $sourceVendorListing->vendor_id,
                    price: (int) $item->unit_price,
                    quantity: (int) $item->quantity,
                    fulfillmentModel: (string) ($sourceVendorListing->fulfillment_model ?? 'fbm'),
                    warehouseInventories: $sourceVendorListing->relationLoaded('warehouseInventories')
                        ? $sourceVendorListing->warehouseInventories
                        : $sourceVendorListing->warehouseInventories()->get(),
                    vendorListingIdForOrderItem: $sourceVendorListing->id,
                    adminListingIdForOrderItem: null,
                    marketerListingIdForOrderItem: $marketerListing->id,
                );
            }

            if ($sourceAdminListing) {
                return new self(
                    cartItem: $item,
                    sellable: $marketerListing,
                    fulfilmentListing: $sourceAdminListing,
                    sellerParty: 'platform',
                    price: (int) $item->unit_price,
                    quantity: (int) $item->quantity,
                    fulfillmentModel: 'fbn',
                    warehouseInventories: $sourceAdminListing->relationLoaded('warehouseInventories')
                        ? $sourceAdminListing->warehouseInventories
                        : $sourceAdminListing->warehouseInventories()->get(),
                    vendorListingIdForOrderItem: null,
                    adminListingIdForOrderItem: $sourceAdminListing->id,
                    marketerListingIdForOrderItem: $marketerListing->id,
                );
            }

            return null;
        }

        return null;
    }

    /**
     * @return array{0: ?VendorListing, 1: ?AdminListing}
     */
    private static function resolveIndependentMarketerFulfilment(\App\Models\MarketerListing $marketerListing): array
    {
        $vendorListing = VendorListing::query()
            ->where('product_variant_id', $marketerListing->product_variant_id)
            ->where('country_id', $marketerListing->country_id)
            ->where('status', \App\Enums\VendorListingStatus::Active)
            ->orderBy('price')
            ->first();

        if ($vendorListing) {
            return [$vendorListing, null];
        }

        $adminListing = AdminListing::query()
            ->where('product_variant_id', $marketerListing->product_variant_id)
            ->where('country_id', $marketerListing->country_id)
            ->active()
            ->orderBy('price')
            ->first();

        return [null, $adminListing];
    }

    public function isAdminSeller(): bool
    {
        return $this->sellerParty === 'platform';
    }

    public function availableQuantity(): int
    {
        return (int) $this->warehouseInventories->sum('quantity_available');
    }

    public function groupKey(?string $shippingMethodId, ?string $warehouseId = null): string
    {
        return $this->sellerParty.'|'.($shippingMethodId ?? '').'|'.($warehouseId ?? '');
    }
}
