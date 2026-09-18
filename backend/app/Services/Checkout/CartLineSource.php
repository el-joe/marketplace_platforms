<?php

namespace App\Services\Checkout;

use App\Exceptions\InternationalShippingIneligibleException;
use App\Models\AdminListing;
use App\Models\CartItem;
use App\Models\InternationalShippingEligibility;
use App\Models\ProductCountry;
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
 *   vendor or admin listing. For a marketer listing this is always its
 *   explicit `source_type`/`source_listing_id` (enhancement.md P-15),
 *   whether the marketer listing is campaign-linked or independent.
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

            // enhancement.md P-15 task 5: source_listing_id is resolved
            // explicitly at listing-creation time (both for campaign-linked
            // and independent marketer listings — see
            // MarketerCampaignService::createProductListing() and
            // Marketer\ListingController@store), and kept in sync by the
            // vendor/admin listing observers. Checkout just reads it — it
            // no longer re-derives a fulfilment listing via the campaign
            // chain or a "best active listing" fallback at cart-resolve
            // time (that runtime fallback let a listing be added to cart
            // and then fail at checkout if the picked seller went out of
            // stock in between; see enhancement.md P-15).
            $sourceVendorListing = $marketerListing->source_type === 'vendor_listing'
                ? VendorListing::find($marketerListing->source_listing_id)
                : null;
            $sourceAdminListing = $marketerListing->source_type === 'admin_listing'
                ? AdminListing::find($marketerListing->source_listing_id)
                : null;

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

    /**
     * docs/plans/international_product_shipping.md Phase 3.
     *
     * The country the fulfilment listing (vendor or admin listing — always
     * has a NOT NULL country_id) ships from. This is what "origin_country_id"
     * means everywhere in the international-shipping feature: the listing's
     * own storefront country, never the marketer/sellable listing.
     */
    public function originCountryId(): ?string
    {
        return $this->fulfilmentListing->country_id ?? null;
    }

    /**
     * A line is "international" when the fulfilment listing's country
     * differs from the order's destination country — mirrors
     * SubOrder::isInternational()'s origin_country_id !== order.country_id
     * definition, just computed pre-order at cart-line time.
     */
    public function isInternational(string $destinationCountryId): bool
    {
        $origin = $this->originCountryId();

        return $origin !== null && $origin !== $destinationCountryId;
    }

    /**
     * Validate that this line's listing is actually allowed to ship to
     * $destinationCountryId. Called only for international lines
     * (isInternational() === true) — a domestic line never needs this.
     *
     * Precedence (per the Phase 3 spec): product_countries availability is
     * an earlier, separate gate ("is this product sellable/visible in
     * country X at all") and is checked first; international_shipping_
     * eligibility ("can this specific listing physically ship there") is
     * checked second. Both must pass.
     *
     * @throws InternationalShippingIneligibleException
     */
    public function assertEligibleForDestination(string $destinationCountryId): void
    {
        if (! $this->isInternational($destinationCountryId)) {
            return;
        }

        $productId = $this->fulfilmentListing->productVariant?->product_id;

        if ($productId !== null) {
            $productCountry = ProductCountry::query()
                ->where('product_id', $productId)
                ->where('country_id', $destinationCountryId)
                ->first();

            if ($productCountry !== null && ! $productCountry->is_available) {
                throw new InternationalShippingIneligibleException(
                    __('common.exceptions.checkout.international_shipping_ineligible'),
                    listingId: $this->fulfilmentListing->id,
                    destinationCountryId: $destinationCountryId,
                );
            }
        }

        $eligibilityQuery = InternationalShippingEligibility::query()
            ->where('destination_country_id', $destinationCountryId)
            ->where('is_active', true);

        if ($this->fulfilmentListing instanceof VendorListing) {
            $eligibilityQuery->where('vendor_listing_id', $this->fulfilmentListing->id);
        } else {
            $eligibilityQuery->where('admin_listing_id', $this->fulfilmentListing->id);
        }

        if (! $eligibilityQuery->exists()) {
            throw new InternationalShippingIneligibleException(
                __('common.exceptions.checkout.international_shipping_ineligible'),
                listingId: $this->fulfilmentListing->id,
                destinationCountryId: $destinationCountryId,
            );
        }
    }
}
