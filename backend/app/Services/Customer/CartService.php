<?php

namespace App\Services\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\VendorListingStatus;
use App\Models\AdminListing;
use App\Models\Cart;
use App\Models\CartInventoryLock;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CountryShippingSetting;
use App\Models\VendorListing;
use App\Services\AppContextService;
use App\Services\ShippingMethodResolverService;

class CartService
{
    private const MAX_ITEMS = 50;

    private static function itemEagerLoads(): array
    {
        return [
            'items.vendorListing.vendor',
            'items.vendorListing.productVariant.product.images' => fn ($q) => $q->orderBy('position')->limit(1),
            'items.vendorListing.primaryShippingMethod',
            'items.vendorListing.warehouseInventories',
            'items.adminListing.productVariant.product.images' => fn ($q) => $q->orderBy('position')->limit(1),
            'items.selectedShippingMethod',
        ];
    }

    public function __construct(
        private readonly CheckoutCalculationService $calculationService,
        private readonly AppContextService $appContextService,
        private readonly ShippingMethodResolverService $shippingMethodResolver,
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

    public function getOrCreateGuestCart(string $sessionToken, string $countryId, string $currency): Cart
    {
        $cart = Cart::where('session_token', $sessionToken)
            ->whereNull('user_id')
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

        return $customerCart->fresh(['items.vendorListing.productVariant.product.images', 'coupon']);
    }

    public function addItem(Cart $cart, string $vendorListingId, int $quantity, ?string $shippingMethodId, string $countryId): CartItem
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
        }

        $this->recalculateCart($cart);

        return $item->fresh();
    }

    /**
     * @param array<int, array{vendor_listing_id?: string, admin_listing_id?: string, listing_type?: string, quantity: int, shipping_method_id?: ?string}> $items
     * @return array<int, CartItem>
     */
    public function addItems(Cart $cart, array $items, string $countryId): array
    {
        $added = [];

        foreach ($items as $item) {
            if (($item['listing_type'] ?? null) === 'admin') {
                $added[] = $this->addAdminItem($cart, $item['admin_listing_id'], $item['quantity'], $item['shipping_method_id'] ?? null, $countryId);
            } else {
                $added[] = $this->addItem($cart, $item['vendor_listing_id'], $item['quantity'], $item['shipping_method_id'] ?? null, $countryId);
            }
        }

        return $added;
    }

    /**
     * Adds a platform-owned admin listing to the cart. Only valid in the
     * nawy_now app context, and only for listings explicitly featured there.
     */
    public function addAdminItem(Cart $cart, string $adminListingId, int $quantity, ?string $shippingMethodId, string $countryId): CartItem
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

    public function applyCoupon(Cart $cart, Customer $customer, string $code): Coupon
    {
        $coupon = Coupon::where('code', $code)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->firstOrFail();

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            throw new \DomainException(__('common.exceptions.cart.coupon_usage_limit_reached'));
        }

        $customerUsageCount = CouponUsage::where('coupon_id', $coupon->id)
            ->where('customer_id', $customer->id)
            ->count();

        if ($customerUsageCount >= $coupon->usage_limit_per_customer) {
            throw new \DomainException(__('common.exceptions.cart.coupon_customer_limit_reached'));
        }

        $subtotal = (int) $cart->items()->get()->sum(fn(CartItem $item) => $item->unit_price * $item->quantity);

        if ($coupon->min_order_amount !== null && $subtotal < $coupon->min_order_amount) {
            $minFormatted = number_format($coupon->min_order_amount, 2);
            throw new \DomainException(__('common.exceptions.cart.coupon_min_order_required', ['amount' => $minFormatted, 'currency' => $cart->currency]));
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
        if ($cart->coupon && $cart->customer) {
            $result = $this->calculationService->applyCoupon(
                $cart->coupon,
                $cart->customer,
                $subtotal,
                $cart->currency,
                $cart->items->all(),
            );
            $discount = $result['error'] ? 0 : $result['discount'];
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
                'selectedShippingMethod',
                'vendorListing.productVariant.product',
                'vendorListing.productVariant.images',
                'adminListing.productVariant.product',
                'adminListing.productVariant.images',
                'warrantyPlan',
            ])
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $groups = $items->groupBy('selected_shipping_method_id');

        return $groups->map(function ($groupItems) use ($countryId) {
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
                'items' => $groupItems->map(function (CartItem $item) use ($method) {
                    $isVendor = (bool) $item->vendor_listing_id;
                    $listing = $isVendor ? $item->vendorListing : $item->adminListing;
                    $variant = $listing?->productVariant;
                    $product = $variant?->product;
                    // Try variant-specific image first; fall back to product-level (variant-agnostic) image.
                    $variantImage  = $variant?->images?->firstWhere('is_primary', true)
                        ?? $variant?->images?->first();
                    $productImage  = $product?->images
                        ?->whereNull('product_variant_id')
                        ->sortBy('position')
                        ->first();
                    $primaryImage  = $variantImage ?? $productImage;

                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->unit_price * $item->quantity,
                        'product_url' => "/products/{$variant?->id}/" . ($isVendor ? $item->vendor_listing_id : $item->admin_listing_id),
                        'product_name_en' => $product?->name_en,
                        'product_name_ar' => $product?->name_ar,
                        'max_order_quantity' => $listing?->max_order_quantity,
                        'variant_name' => $variant?->variant_name,
                        'primary_image' => $primaryImage?->path,
                        'listing_id' => $isVendor ? $item->vendor_listing_id : $item->admin_listing_id,
                        'listing_type' => $isVendor ? 'vendor' : 'admin',
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
