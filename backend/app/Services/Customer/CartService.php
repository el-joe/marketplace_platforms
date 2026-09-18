<?php

namespace App\Services\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\VendorListingStatus;
use App\Models\AdminListing;
use App\Models\Cart;
use App\Models\CartInventoryLock;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CountryShippingSetting;
use App\Models\VendorListing;
use App\Services\AppContextService;
use App\Services\Media\ListingImageResolver;
use App\Services\ShippingMethodResolverService;

class CartService
{
    private const MAX_ITEMS = 50;

    private static function itemEagerLoads(): array
    {
        return [
            'items.vendorListing.vendor',
            'items.vendorListing.productVariant.product.images',
            'items.vendorListing.productVariant.images',
            'items.vendorListing.primaryShippingMethod',
            'items.vendorListing.warehouseInventories',
            'items.adminListing.productVariant.product.images',
            'items.adminListing.productVariant.images',
            'items.marketerListing.productVariant.product.images',
            'items.marketerListing.productVariant.images',
            'items.selectedShippingMethod',
        ];
    }

    public function __construct(
        private readonly CheckoutCalculationService $calculationService,
        private readonly AppContextService $appContextService,
        private readonly ShippingMethodResolverService $shippingMethodResolver,
        private readonly ListingImageResolver $imageResolver,
    ) {
    }

    /**
     * Validates the requested shipping method against the listing's available
     * methods, or falls back to the listing's default method when none was
     * requested.
     */
    private function resolveShippingMethodId(string $listingId, string $listingType, string $countryId, ?string $shippingMethodId): ?string
    {
        if ($shippingMethodId) {
            $valid = $this->shippingMethodResolver->validateMethodForListing($shippingMethodId, $listingId, $listingType, $countryId);
            if (!$valid) {
                throw new \DomainException(__('common.exceptions.cart.invalid_shipping_method'));
            }

            return $shippingMethodId;
        }

        return $this->shippingMethodResolver->getDefaultForListing($listingId, $listingType, $countryId)?->id;
    }

    public function getOrCreateCart(Customer $customer, string $countryId, string $currency): Cart
    {
        $cart = Cart::firstOrCreate(
            ['user_id' => $customer->id, 'country_id' => $countryId],
            [
                'currency' => $currency,
                'subtotal' => 0,
                'discount' => 0,
                'estimated_shipping' => 0,
                'estimated_tax' => 0,
                'estimated_total' => 0,
            ]
        );

        $this->recalculateCart($cart);

        return $cart;
    }

    /**
     * Carts are scoped per (identity, country_id) — a customer/guest gets one
     * cart per country, mirroring getOrCreateCart()'s authed lookup. This
     * keeps country-scoping consistent between the two identity paths (a
     * guest switching country context gets/creates their own cart for that
     * country, same as an authed customer would), rather than the guest path
     * previously ignoring country_id and always returning whichever single
     * cart the session_token pointed at regardless of country.
     */
    public function getOrCreateGuestCart(string $sessionToken, string $countryId, string $currency): Cart
    {
        $cart = Cart::where('session_token', $sessionToken)
            ->whereNull('user_id')
            ->where('country_id', $countryId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['items.vendorListing', 'coupon'])
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'session_token' => $sessionToken,
                'user_id' => null,
                'country_id' => $countryId,
                'currency' => $currency,
                'subtotal' => 0,
                'discount' => 0,
                'estimated_shipping' => 0,
                'estimated_tax' => 0,
                'estimated_total' => 0,
                'expires_at' => now()->addDays(30),
            ]);
        }

        return $cart;
    }

    public function mergeGuestCart(string $sessionToken, Customer $customer, string $countryId, string $currency): Cart
    {
        $guestCart = Cart::where('session_token', $sessionToken)
            ->whereNull('user_id')
            ->with('items')
            ->first();

        $customerCart = $this->getOrCreateCart($customer, $countryId, $currency);

        if (!$guestCart || $guestCart->items->isEmpty()) {
            return $customerCart;
        }

        foreach ($guestCart->items as $guestItem) {
            $existing = $customerCart->items
                ->firstWhere('vendor_listing_id', $guestItem->vendor_listing_id);

            if ($existing) {
                $existing->update([
                    'quantity' => max($existing->quantity, $guestItem->quantity),
                ]);
            } else {
                $guestItem->update(['cart_id' => $customerCart->id]);
            }
        }

        CartInventoryLock::where('cart_id', $guestCart->id)->delete();
        $guestCart->items()->whereNot('cart_id', $customerCart->id)->delete();
        $guestCart->delete();

        $customerCart->load(['items.vendorListing', 'coupon']);
        $this->recalculateCart($customerCart);

        return $customerCart->fresh(array_merge(self::itemEagerLoads(), ['coupon']));
    }

    /**
     * Validates that every is_required=true ProductCustomAttribute on the
     * product has a corresponding value in $values, then persists each
     * incoming value as a CartItemCustomAttributeValue row for the given
     * (newly created) cart item. Never touches the `attribute_values` table
     * — that table is reserved for variant-defining pick-list attributes.
     *
     * @param array<int, array{product_custom_attribute_id: string, value: string}> $values
     */
    private function applyCustomAttributeValues(CartItem $item, \App\Models\Product $product, array $values): void
    {
        $requiredIds = $product->customAttributes()
            ->where('is_required', true)
            ->pluck('id')
            ->all();

        $providedIds = array_column($values, 'product_custom_attribute_id');

        foreach ($requiredIds as $requiredId) {
            if (!in_array($requiredId, $providedIds, true)) {
                throw new \DomainException(__('common.exceptions.cart.custom_attribute_required'));
            }
        }

        foreach ($values as $value) {
            if (empty($value['product_custom_attribute_id']) || !isset($value['value']) || $value['value'] === '') {
                continue;
            }

            $item->customAttributeValues()->create([
                'product_custom_attribute_id' => $value['product_custom_attribute_id'],
                'value' => $value['value'],
            ]);
        }
    }

    public function addItem(Cart $cart, string $vendorListingId, int $quantity, ?string $shippingMethodId, string $countryId, array $customAttributeValues = []): CartItem
    {
        $listing = VendorListing::with(['warehouseInventories', 'productVariant.product'])->findOrFail($vendorListingId);

        $shippingMethodId = $this->resolveShippingMethodId($vendorListingId, 'vendor_listing', $countryId, $shippingMethodId);

        $available = $listing->warehouseInventories->sum('quantity_available');
        if ($available < $quantity) {
            throw new \DomainException(__('common.exceptions.cart.insufficient_stock', ['available' => $available]));
        }

        $currentCount = $cart->items()->count();

        $existingItem = $cart->items()->where('vendor_listing_id', $vendorListingId)->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $quantity;
            if ($newQty > ($listing->max_order_quantity ?? PHP_INT_MAX)) {
                throw new \DomainException(__('common.exceptions.cart.exceeds_max_order_quantity'));
            }
            if ($available < $newQty) {
                throw new \DomainException(__('common.exceptions.cart.insufficient_stock', ['available' => $available]));
            }
            $updates = ['quantity' => $newQty];
            if ($shippingMethodId !== null) {
                $updates['selected_shipping_method_id'] = $shippingMethodId;
            }
            $existingItem->update($updates);
            $item = $existingItem;
        } else {
            if ($currentCount >= self::MAX_ITEMS) {
                throw new \DomainException(__('common.exceptions.cart.max_items', ['max' => self::MAX_ITEMS]));
            }
            $item = $cart->items()->create([
                'vendor_listing_id' => $vendorListingId,
                'quantity' => $quantity,
                'unit_price' => $listing->price,
                'added_at' => now(),
                'selected_shipping_method_id' => $shippingMethodId,
            ]);

            $this->applyCustomAttributeValues($item, $listing->productVariant->product, $customAttributeValues);
        }

        $this->recalculateCart($cart);

        return $item->fresh();
    }

    /**
     * @param array<int, array{vendor_listing_id?: string, admin_listing_id?: string, listing_type?: string, quantity: int, shipping_method_id?: ?string, custom_attribute_values?: array}> $items
     * @return array<int, CartItem>
     */
    public function addItems(Cart $cart, array $items, string $countryId): array
    {
        $added = [];

        foreach ($items as $item) {
            if (($item['listing_type'] ?? null) === 'admin') {
                $added[] = $this->addAdminItem($cart, $item['admin_listing_id'], $item['quantity'], $item['shipping_method_id'] ?? null, $countryId, $item['custom_attribute_values'] ?? []);
            } else {
                $added[] = $this->addItem($cart, $item['vendor_listing_id'], $item['quantity'], $item['shipping_method_id'] ?? null, $countryId, $item['custom_attribute_values'] ?? []);
            }
        }

        return $added;
    }

    /**
     * Adds a platform-owned admin listing to the cart. Only valid in the
     * nawy_now app context, and only for listings explicitly featured there.
     */
    public function addAdminItem(Cart $cart, string $adminListingId, int $quantity, ?string $shippingMethodId, string $countryId, array $customAttributeValues = []): CartItem
    {
        if (!$this->appContextService->isNawyNow()) {
            throw new \DomainException(__('common.exceptions.cart.admin_listing_not_allowed'));
        }

        $listing = AdminListing::with(['warehouseInventories', 'productVariant.product'])->findOrFail($adminListingId);

        if ($listing->status !== AdminListingStatus::Active) {
            throw new \DomainException(__('common.exceptions.cart.admin_listing_not_allowed'));
        }

        $shippingMethodId = $this->resolveShippingMethodId($adminListingId, 'admin_listing', $countryId, $shippingMethodId);

        $available = $listing->warehouseInventories->sum('quantity_available');
        if ($available < $quantity) {
            throw new \DomainException(__('common.exceptions.cart.insufficient_stock', ['available' => $available]));
        }

        $currentCount = $cart->items()->count();

        $existingItem = $cart->items()->where('admin_listing_id', $adminListingId)->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $quantity;
            if ($newQty > ($listing->max_order_quantity ?? PHP_INT_MAX)) {
                throw new \DomainException(__('common.exceptions.cart.exceeds_max_order_quantity'));
            }
            if ($available < $newQty) {
                throw new \DomainException(__('common.exceptions.cart.insufficient_stock', ['available' => $available]));
            }
            $updates = ['quantity' => $newQty];
            if ($shippingMethodId !== null) {
                $updates['selected_shipping_method_id'] = $shippingMethodId;
            }
            $existingItem->update($updates);
            $item = $existingItem;
        } else {
            if ($currentCount >= self::MAX_ITEMS) {
                throw new \DomainException(__('common.exceptions.cart.max_items', ['max' => self::MAX_ITEMS]));
            }
            $item = $cart->items()->create([
                'vendor_listing_id' => null,
                'admin_listing_id' => $adminListingId,
                'quantity' => $quantity,
                'unit_price' => $listing->getRawOriginal('price'),
                'added_at' => now(),
                'selected_shipping_method_id' => $shippingMethodId,
            ]);

            $this->applyCustomAttributeValues($item, $listing->productVariant->product, $customAttributeValues);
        }

        $this->recalculateCart($cart);

        return $item->fresh();
    }

    /**
     * Adds a marketer listing (a marketer's promoted product) to the cart.
     * Marketer listings have no independent shipping/inventory of their own —
     * they ride on the underlying vendor/admin listing's fulfillment via checkout.
     */
    public function addMarketerItem(Cart $cart, string $marketerListingId, int $quantity, string $countryId): CartItem
    {
        $listing = \App\Models\MarketerListing::with(['productVariant.product'])
            ->where('id', $marketerListingId)
            ->where('country_id', $countryId)
            ->where('status', 'active')
            ->firstOrFail();

        $currentCount = $cart->items()->count();

        $existingItem = $cart->items()->where('marketer_listing_id', $marketerListingId)->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $quantity;
            $existingItem->update(['quantity' => $newQty]);
            $item = $existingItem;
        } else {
            if ($currentCount >= self::MAX_ITEMS) {
                throw new \DomainException(__('common.exceptions.cart.max_items', ['max' => self::MAX_ITEMS]));
            }
            $item = $cart->items()->create([
                'marketer_listing_id' => $marketerListingId,
                'vendor_listing_id'   => null,
                'admin_listing_id'    => null,
                'quantity'            => $quantity,
                'unit_price'          => $listing->price,
                'added_at'            => now(),
            ]);
        }

        $this->recalculateCart($cart);

        return $item->fresh();
    }

    /**
     * $shippingMethodProvided distinguishes "client omitted shipping_method_id"
     * (leave the item's current selection untouched) from "client explicitly
     * sent it" (validate and apply, auto-assigning the default when null).
     */
    public function updateItem(Cart $cart, string $itemId, int $quantity, ?string $shippingMethodId, bool $shippingMethodProvided, string $countryId): CartItem
    {
        $item = $cart->items()->findOrFail($itemId);

        if ($item->marketer_listing_id !== null) {
            \App\Models\MarketerListing::where('id', $item->marketer_listing_id)
                ->where('status', 'active')
                ->firstOrFail();

            $item->update(['quantity' => $quantity]);
            $this->recalculateCart($cart);

            return $item->fresh();
        }

        $listingId = $item->vendor_listing_id ?? $item->admin_listing_id;
        $listingType = $item->vendor_listing_id ? 'vendor_listing' : 'admin_listing';
        $listing = $item->vendor_listing_id
            ? VendorListing::with(['warehouseInventories', 'productVariant.product'])->findOrFail($listingId)
            : AdminListing::with(['warehouseInventories', 'productVariant.product'])->findOrFail($listingId);

        $updates = ['quantity' => $quantity];
        if ($shippingMethodProvided) {
            $updates['selected_shipping_method_id'] = $this->resolveShippingMethodId($listingId, $listingType, $countryId, $shippingMethodId);
        }

        $available = $listing->warehouseInventories->sum('quantity_available');
        if ($available < $quantity) {
            throw new \DomainException(__('common.exceptions.cart.insufficient_stock', ['available' => $available]));
        }
        if ($quantity > ($listing->max_order_quantity ?? PHP_INT_MAX)) {
            throw new \DomainException(__('common.exceptions.cart.exceeds_max_order_quantity'));
        }

        $item->update($updates);
        $this->recalculateCart($cart);

        return $item->fresh();
    }

    public function removeItem(Cart $cart, string $itemId): void
    {
        $cart->items()->findOrFail($itemId)->delete();
        $this->recalculateCart($cart);
    }

    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_id' => null, 'affiliate_promo_code_id' => null]);
        $this->recalculateCart($cart);
    }

    /**
     * $customer is null for guest carts (auth('customer')->user() is null
     * for guests). Eligibility (active/date range, country, currency,
     * min-order, customer_eligibility, usage limits, shipping-type
     * restriction) is fully delegated to
     * CheckoutCalculationService::applyCoupon() -> CheckoutPricingEngine so
     * this is the *same* validation recalculateCart() re-runs on every
     * subsequent cart load — a coupon that passes here cannot later fail
     * silently at recalculation for a reason this method didn't already
     * check (see recalculateCart()).
     */
    public function applyCoupon(Cart $cart, ?Customer $customer, string $code): Coupon
    {
        $coupon = Coupon::where('code', $code)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->firstOrFail();

        $subtotal = (int) $cart->items()->get()->sum(fn(CartItem $item) => $item->unit_price * $item->quantity);

        $result = $this->calculationService->applyCoupon(
            $coupon,
            $customer,
            $subtotal,
            $cart->currency,
            $cart->items()->get()->all(),
            $cart->country_id,
        );

        if ($result['error']) {
            throw new \DomainException($result['error']);
        }

        $cart->update(['coupon_id' => $coupon->id]);
        $this->recalculateCart($cart);

        return $coupon;
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_id' => null]);
        $this->recalculateCart($cart);
    }

    /**
     * Recalculates cart totals from scratch using live vendor_listing prices.
     * Called after every cart mutation so totals can never go stale.
     *
     * Syncs unit_price to the listing's current price, drops items whose
     * listing is no longer active, recomputes discount via
     * CheckoutCalculationService (coupon scope/eligibility can change between
     * requests), and recomputes tax from the cart's country VAT rate.
     *
     * Annotates each surviving CartItem with a transient (non-persisted)
     * `price_changed` attribute so the API response can flag "price updated"
     * items to the client.
     */
    private function recalculateCart(Cart $cart): void
    {
        $cart->load(array_merge(self::itemEagerLoads(), ['coupon', 'customer']));

        $priceChanges = [];

        foreach ($cart->items as $item) {
            if ($item->marketer_listing_id !== null) {
                $listing = $item->marketerListing;

                if (!$listing || $listing->status !== 'active') {
                    $item->delete();
                    continue;
                }

                $livePrice = (int) $listing->price;

                if ((int) $item->unit_price !== $livePrice) {
                    $priceChanges[$item->id] = true;
                    $item->update(['unit_price' => $livePrice]);
                }

                continue;
            }

            if ($item->admin_listing_id !== null) {
                $listing = $item->adminListing;

                if (!$listing || $listing->status !== AdminListingStatus::Active) {
                    $item->delete();
                    continue;
                }

                $livePrice = (int) $listing->getRawOriginal('price');
            } else {
                $listing = $item->vendorListing;

                if (!$listing || $listing->status !== VendorListingStatus::Active) {
                    $item->delete();
                    continue;
                }

                $livePrice = (int) $listing->price;
            }

            if ((int) $item->unit_price !== $livePrice) {
                $priceChanges[$item->id] = true;
                $item->update(['unit_price' => $livePrice]);
            }
        }

        $cart->unsetRelation('items');
        $cart->load(self::itemEagerLoads());

        $subtotal = (int) $cart->items->sum(fn(CartItem $item) => $item->unit_price * $item->quantity);

        $discount = 0;
        $couponError = null;
        if ($cart->coupon) {
            // Same validation applyCoupon() ran when the coupon was first
            // attached (CheckoutCalculationService -> CheckoutPricingEngine
            // is the single source of truth used at both apply-time and
            // recalculation-time), so a coupon accepted here cannot silently
            // fail later for a reason apply-time didn't already check. It
            // can still legitimately stop being eligible between requests
            // (e.g. cart contents changed so min_order_amount is no longer
            // met, or the cart's country/currency changed) — in that case we
            // detach the coupon and surface why, instead of leaving
            // coupon_id set while quietly showing a 0 discount.
            $result = $this->calculationService->applyCoupon(
                $cart->coupon,
                $cart->customer,
                $subtotal,
                $cart->currency,
                $cart->items->all(),
                $cart->country_id,
            );

            if ($result['error']) {
                $couponError = $result['error'];
                $cart->update(['coupon_id' => null]);
                $cart->unsetRelation('coupon');
            } else {
                $discount = $result['discount'];
            }
        }

        $country = Country::find($cart->country_id);
        $taxable = max(0, $subtotal - $discount);
        $estimatedTax = $country ? $this->calculationService->calculateTax($taxable, $country) : 0;

        $cart->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'estimated_shipping' => 0,
            'estimated_tax' => $estimatedTax,
            'estimated_total' => max(0, $subtotal - $discount + $estimatedTax),
            'affiliate_promo_code_id' => $cart->affiliate_promo_code_id,
            'expires_at' => now()->addDays(30),
        ]);

        foreach ($cart->items as $item) {
            $item->setAttribute('price_changed', $priceChanges[$item->id] ?? false);
        }

        // Transient, non-persisted (there is no coupon_error column). Synced
        // as "original" immediately so it never gets swept into a later,
        // unrelated $cart->update()/save() call's dirty-attribute diff in
        // the same request (which would otherwise fail with "Unknown column
        // 'coupon_error'").
        $cart->setAttribute('coupon_error', $couponError);
        $cart->syncOriginalAttribute('coupon_error');
    }

    /**
     * Groups cart items by their selected_shipping_method_id so the app can
     * render a Noon-style cart with items grouped under their delivery
     * method. Items with no selection yet fall into a shared "unassigned"
     * group (null method).
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildShippingGroups(Cart $cart, ?string $countryId): array
    {
        $items = CartItem::where('cart_id', $cart->id)
            ->with([
                'vendorListing.vendor',
                'adminListing',
                'marketerListing',
                'selectedShippingMethod',
                'vendorListing.productVariant.product',
                'vendorListing.productVariant.images',
                'adminListing.productVariant.product',
                'adminListing.productVariant.images',
                'marketerListing.productVariant.product.images',
                'marketerListing.productVariant.images',
                'warrantyPlan',
            ])
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $groups = $items->groupBy('selected_shipping_method_id');

        $variantIds = $items->map(function (CartItem $item) {
            $isMarketer = (bool) $item->marketer_listing_id;
            $isVendor   = !$isMarketer && (bool) $item->vendor_listing_id;
            $listing    = $isMarketer
                ? $item->marketerListing
                : ($isVendor ? $item->vendorListing : $item->adminListing);

            return $listing?->productVariant?->id;
        })->filter()->unique()->values();

        $imagesByVariant = $this->imageResolver->forVariants($variantIds);

        return $groups->map(function ($groupItems) use ($countryId, $imagesByVariant) {
            $method = $groupItems->first()->selectedShippingMethod;

            $groupSubtotal = $groupItems->sum(fn(CartItem $item) => $item->unit_price * $item->quantity);

            $threshold = ($method && $countryId)
                ? CountryShippingSetting::where('country_id', $countryId)
                    ->where('shipping_method_id', $method->id)
                    ->value('free_shipping_threshold')
                : null;

            $isFreeShipping = $threshold !== null && $groupSubtotal >= $threshold;

            return [
                'shipping_method' => $method ? [
                    'id' => $method->id,
                    'name' => $method->name,
                    'code' => $method->code,
                    'badge_label_en' => $method->badge_label_en,
                    'badge_label_ar' => $method->badge_label_ar,
                    'badge_color_hex' => $method->badge_color_hex,
                    'delivery_label_en' => $method->delivery_label_en,
                    'delivery_label_ar' => $method->delivery_label_ar,
                    'is_express_type' => (bool) $method->is_express_type,
                ] : null,
                'is_free_shipping' => $isFreeShipping,
                'group_subtotal' => $groupSubtotal,
                'items_count' => $groupItems->count(),
                'items' => $groupItems->map(function (CartItem $item) use ($method, $imagesByVariant) {
                    $isMarketer = (bool) $item->marketer_listing_id;
                    $isVendor   = !$isMarketer && (bool) $item->vendor_listing_id;
                    $listing    = $isMarketer
                        ? $item->marketerListing
                        : ($isVendor ? $item->vendorListing : $item->adminListing);
                    $listingType = $isMarketer ? 'marketer' : ($isVendor ? 'vendor' : 'admin');
                    $variant = $listing?->productVariant;
                    $product = $variant?->product;
                    // ListingImageResolver applies the variant-first / product-fallback rule.
                    $images = $variant ? ($imagesByVariant[$variant->id] ?? []) : [];
                    $primaryImageUrl = $images[0]->url ?? null;

                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->unit_price * $item->quantity,
                        'product_url' => "/products/{$variant?->id}/" . ($isMarketer ? $item->marketer_listing_id : ($isVendor ? $item->vendor_listing_id : $item->admin_listing_id)),
                        'product_name_en' => $product?->name_en,
                        'product_name_ar' => $product?->name_ar,
                        'max_order_quantity' => $listing?->max_order_quantity,
                        'variant_name' => ($variant && $product) ? $variant->setRelation('product', $product)->displayName() : $variant?->variant_name,
                        'primary_image' => $primaryImageUrl,
                        'image' => $primaryImageUrl ? ['url' => $primaryImageUrl, 'alt' => $images[0]->alt ?? ['ar' => null, 'en' => null]] : null,
                        'images' => array_map(fn ($i) => $i->toArray(), $images),
                        'listing_id' => $isMarketer ? $item->marketer_listing_id : ($isVendor ? $item->vendor_listing_id : $item->admin_listing_id),
                        'listing_type' => $listingType,
                        'vendor' => $isVendor ? [
                            'id' => $item->vendorListing->vendor->id,
                            'store_name' => $item->vendorListing->vendor->store_name,
                        ] : null,
                        'selected_shipping_method' => [
                            'id' => $method?->id,
                            'name' => $method?->name,
                            'code' => $method?->code,
                        ],
                        'warranty_plan' => $item->warrantyPlan ? [
                            'id' => $item->warrantyPlan->id,
                            'name' => $item->warrantyPlan->localized_name,
                            'duration_months' => $item->warrantyPlan->duration_months,
                            'duration_label' => $item->warrantyPlan->duration_label,
                            'price' => $item->warrantyPlan->resolvePrice((int) $item->unit_price),
                            'currency' => $item->warrantyPlan->currency,
                            'image_url' => $item->warrantyPlan->image_url,
                        ] : null,
                    ];
                })->values(),
            ];
        })->values()->all();
    }
}
