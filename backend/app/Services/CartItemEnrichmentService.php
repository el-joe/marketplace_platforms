<?php

namespace App\Services;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\GlobalSystemType;
use App\Models\AdminListing;
use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\FlashSaleSubmission;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\VendorListing;
use App\Models\WarrantyPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-enriches cart items with delivery estimates, coupon eligibility,
 * stock status and warranty/free-shipping badges for the cart response.
 *
 * All money fields are BIGINT base currency units — never divided or
 * multiplied by 100 here. Cost/payout/commission fields are never read
 * or exposed. Everything needed for the per-item mapping loop is batch
 * loaded up front to avoid N+1 queries.
 */
class CartItemEnrichmentService
{
    private const GROUP_ORDER = [
        'express' => 0,
        'same_day' => 1,
        'standard' => 2,
    ];

    /**
     * @param Collection<int, \App\Models\CartItem> $cartItems
     * @return array<int, array<string, mixed>>
     */
    public function enrich(Collection $cartItems, ?Customer $customer, ?\App\Models\Country $country): array
    {
        $timezone = $country?->timezone ?? 'Asia/Dubai';

        $vendorItems = $cartItems->filter(fn ($item) => empty($item->admin_listing_id));
        $adminItems = $cartItems->filter(fn ($item) => !empty($item->admin_listing_id));

        $vendorListingIds = $vendorItems->pluck('vendor_listing_id')->filter()->unique()->values();
        $adminListingIds = $adminItems->pluck('admin_listing_id')->filter()->unique()->values();

        $vendorListings = VendorListing::with([
            'vendor',
            'productVariant.product.category',
            'productVariant.attributeValues.attribute',
            'productVariant.images',
            'primaryShippingMethod',
        ])->whereIn('id', $vendorListingIds)->get()->keyBy('id');

        $adminListings = AdminListing::with([
            'productVariant.product.category',
            'productVariant.attributeValues.attribute',
            'productVariant.images',
        ])->whereIn('id', $adminListingIds)->get()->keyBy('id');

        $categoryIds = $vendorListings->merge($adminListings)
            ->map(fn ($listing) => $listing->productVariant?->product?->category_id)
            ->filter()
            ->unique()
            ->values();

        $vendorIds = $vendorListings->pluck('vendor_id')->filter()->unique()->values();

        // ── Inventory sums (vendor path only). quantity_available is a MySQL
        // GENERATED VIRTUAL column — never write it; here we aggregate the
        // underlying on_hand/reserved columns ourselves rather than SUM()ing
        // the virtual column directly. ──────────────────────────────────────
        $inventoryByListing = $vendorListingIds->isEmpty() ? collect() : DB::table('warehouse_inventories')
            ->whereIn('vendor_listing_id', $vendorListingIds)
            ->selectRaw('vendor_listing_id, SUM(quantity_on_hand - quantity_reserved) as available')
            ->groupBy('vendor_listing_id')
            ->pluck('available', 'vendor_listing_id');

        // ── Shipping methods: full table (small, reference data) + every
        // category_shipping_methods row (not just defaults) so per-item
        // available-methods lists and the fbn/fbp-filtered fallback chain
        // can be built without further queries. ─────────────────────────────
        $shippingMethods = ShippingMethod::where('is_active', true)->get()->keyBy('id');

        $categoryMethodRows = $categoryIds->isEmpty() ? collect() : DB::table('category_shipping_methods')
            ->whereIn('category_id', $categoryIds)
            ->get()
            ->groupBy('category_id');

        $categoryDefaultMethodId = $categoryIds->isEmpty() ? collect() : DB::table('category_shipping_methods')
            ->where('is_default', true)
            ->whereIn('category_id', $categoryIds)
            ->pluck('shipping_method_id', 'category_id');

        // ── Destination zone (for shipping fee lookups) resolved once from
        // the customer's default address, plus every rate for the zone so
        // fees are looked up in-memory per item. ─────────────────────────────
        $destinationZoneId = $this->resolveDestinationZoneId($customer);

        $ratesByMethodId = $destinationZoneId === null ? collect() : ShippingRate::where('destination_zone_id', $destinationZoneId)
            ->where('is_active', true)
            ->get()
            ->keyBy('shipping_method_id');

        // ── Active flash sales for these vendor listings ────────────────────
        $flashSubmissions = $vendorListingIds->isEmpty() ? collect() : FlashSaleSubmission::with('flashSale')
            ->whereIn('vendor_listing_id', $vendorListingIds)
            ->where('status', 'live')
            ->whereHas('flashSale', function ($q) {
                $q->where('status', 'live')
                    ->where('sale_starts_at', '<=', now())
                    ->where('sale_ends_at', '>=', now());
            })
            ->get()
            ->keyBy('vendor_listing_id');

        // ── Active coupons in scope (platform / this vendor / these
        // categories). Product-scoped coupons are not yet supported by this
        // enrichment — always excluded (coupon_products exists, but per-item
        // product matching is a separate follow-up). ───────────────────────
        $activeCoupons = Coupon::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($q) use ($vendorIds, $categoryIds) {
                $q->where('scope', CouponScope::Platform->value)
                    ->orWhere(function ($q2) use ($vendorIds) {
                        $q2->where('scope', CouponScope::Vendor->value)->whereIn('vendor_id', $vendorIds);
                    })
                    ->orWhere(function ($q3) use ($categoryIds) {
                        $q3->where('scope', CouponScope::Category->value)->whereIn('category_id', $categoryIds);
                    });
            })
            ->get();

        $usedCouponCounts = collect();
        if ($customer && $activeCoupons->isNotEmpty()) {
            $usedCouponCounts = CouponUsage::where('customer_id', $customer->id)
                ->whereIn('coupon_id', $activeCoupons->pluck('id'))
                ->selectRaw('coupon_id, count(*) as used_count')
                ->groupBy('coupon_id')
                ->pluck('used_count', 'coupon_id');
        }

        // ── Warranty plans, resolved per root category. Root is resolved by
        // walking parent_id in PHP against a fully preloaded id => parent_id
        // map, so no additional queries are issued per item. ────────────────
        $categoryParentMap = Category::query()->pluck('parent_id', 'id');
        $rootCategoryIdByCategoryId = [];
        foreach ($categoryIds as $categoryId) {
            $rootCategoryIdByCategoryId[$categoryId] = $this->resolveRootCategoryId($categoryId, $categoryParentMap);
        }
        $rootCategoryIds = collect($rootCategoryIdByCategoryId)->filter()->unique()->values();

        $warrantyPlansByRootCategory = $rootCategoryIds->isEmpty() ? collect() : WarrantyPlan::active()
            ->whereIn('category_id', $rootCategoryIds)
            ->when($country, fn ($q) => $q->forCountry($country->id))
            ->get()
            ->groupBy('category_id');

        return $cartItems->map(function ($item) use (
            $vendorListings,
            $adminListings,
            $inventoryByListing,
            $shippingMethods,
            $categoryDefaultMethodId,
            $categoryMethodRows,
            $categoryParentMap,
            $ratesByMethodId,
            $destinationZoneId,
            $flashSubmissions,
            $activeCoupons,
            $usedCouponCounts,
            $rootCategoryIdByCategoryId,
            $warrantyPlansByRootCategory,
            $timezone,
        ) {
            $isAdminListing = !empty($item->admin_listing_id);
            $listing = $isAdminListing
                ? $adminListings->get($item->admin_listing_id)
                : $vendorListings->get($item->vendor_listing_id);

            if (!$listing) {
                return $this->emptyItemShape($item);
            }

            return $this->buildEnrichedItem(
                $item,
                $listing,
                $isAdminListing,
                $inventoryByListing,
                $shippingMethods,
                $categoryDefaultMethodId,
                $categoryMethodRows,
                $categoryParentMap,
                $ratesByMethodId,
                $destinationZoneId,
                $flashSubmissions,
                $activeCoupons,
                $usedCouponCounts,
                $rootCategoryIdByCategoryId,
                $warrantyPlansByRootCategory,
                $timezone,
            );
        })->values()->all();
    }

    /**
     * Resolves the customer's shipping zone from their default address's
     * city. Returns null when unresolvable — callers must render fees as
     * null (never 0) in that case.
     */
    private function resolveDestinationZoneId(?Customer $customer): ?string
    {
        if (!$customer) {
            return null;
        }

        $address = Address::where('addressable_type', Customer::class)
            ->where('addressable_id', $customer->id)
            ->where('is_default', true)
            ->with('city:id,shipping_zone_id')
            ->first();

        return $address?->city?->shipping_zone_id;
    }

    /**
     * Walks up the category's parent chain (using the fully preloaded
     * id => parent_id map) to find the nearest ancestor that has
     * category_shipping_methods rows. Mirrors ListingShippingResolver's
     * ancestor walk so a leaf category with no rows still resolves.
     */
    private function resolveCategoryMethodRows(?string $categoryId, Collection $categoryMethodRows, Collection $categoryParentMap): Collection
    {
        $current = $categoryId;
        $visited = [];

        while ($current) {
            if (isset($visited[$current])) {
                break;
            }
            $visited[$current] = true;

            $rows = $categoryMethodRows->get($current);
            if ($rows && $rows->isNotEmpty()) {
                return $rows;
            }

            $current = $categoryParentMap->get($current);
        }

        return collect();
    }

    /**
     * Groups enriched items by their resolved primary shipping method.
     * Order: express, same_day, standard, then other methods by
     * display_priority ASC, then a fallback group (unresolvable method)
     * last. This grouped shape is the only cart items shape returned.
     *
     * @param array<int, array<string, mixed>> $enrichedItems
     * @return array<int, array<string, mixed>>
     */
    public function groupByShippingMethod(array $enrichedItems): array
    {
        $groups = [];

        foreach ($enrichedItems as $item) {
            $method = $item['delivery']['primary_method'] ?? null;
            $key = $method['id'] ?? 'fallback';

            if (!isset($groups[$key])) {
                $isUnassigned = $key === 'fallback';

                $groups[$key] = [
                    'shipping_method' => $method,
                    'group_estimate' => $item['delivery']['estimate'] ?? null,
                    'is_unassigned' => $isUnassigned,
                    'unassigned_message_en' => $isUnassigned ? 'Please select a delivery method for these items' : null,
                    'unassigned_message_ar' => $isUnassigned ? 'يرجى اختيار طريقة توصيل لهذه المنتجات' : null,
                    'display_priority' => $item['_display_priority'] ?? null,
                    'items' => [],
                ];
            }

            unset($item['_display_priority']);
            $groups[$key]['items'][] = $item;
        }

        $groupList = array_values($groups);

        usort($groupList, function ($a, $b) {
            $rankA = $this->groupRank($a);
            $rankB = $this->groupRank($b);

            return $rankA <=> $rankB;
        });

        foreach ($groupList as &$group) {
            unset($group['display_priority']);
        }

        return $groupList;
    }

    private function groupRank(array $group): float
    {
        $method = $group['shipping_method'];

        if (!$method) {
            return 999999;
        }

        $code = $method['code'] ?? null;

        if (isset(self::GROUP_ORDER[$code])) {
            return self::GROUP_ORDER[$code];
        }

        return 100 + (float) ($group['display_priority'] ?? 0);
    }

    private function buildEnrichedItem(
        $item,
        $listing,
        bool $isAdminListing,
        Collection $inventoryByListing,
        Collection $shippingMethods,
        Collection $categoryDefaultMethodId,
        Collection $categoryMethodRows,
        Collection $categoryParentMap,
        Collection $ratesByMethodId,
        ?string $destinationZoneId,
        Collection $flashSubmissions,
        Collection $activeCoupons,
        Collection $usedCouponCounts,
        array $rootCategoryIdByCategoryId,
        Collection $warrantyPlansByRootCategory,
        string $timezone,
    ): array {
        $variant = $listing->productVariant;
        $product = $variant?->product;
        $categoryId = $product?->category_id;

        $rows = $this->resolveCategoryMethodRows($categoryId, $categoryMethodRows, $categoryParentMap);

        $shippingMethod = $this->resolvePrimaryShippingMethod(
            $item,
            $listing,
            $isAdminListing,
            $shippingMethods,
            $rows,
        );

        $estimate = $shippingMethod ? $this->computeDeliveryEstimate($shippingMethod, $timezone, $shippingMethods, $categoryId, $categoryDefaultMethodId) : null;

        $availableMethods = $this->buildAvailableMethods(
            $listing,
            $isAdminListing,
            $rows,
            $shippingMethods,
            $shippingMethod,
            $ratesByMethodId,
            $destinationZoneId,
            $timezone,
        );

        $flashSale = null;
        if (!$isAdminListing) {
            $flashSale = $this->buildFlashSaleShape($flashSubmissions->get($listing->id));
        }

        $inventoryAvailable = $isAdminListing ? null : $inventoryByListing->get($listing->id);
        $stock = $this->stockStatus($listing, $isAdminListing, $inventoryAvailable, (int) $item->quantity);

        $coupons = $isAdminListing ? [] : $this->eligibleCouponsForItem(
            $listing,
            $activeCoupons,
            $usedCouponCounts,
            $categoryId,
        );

        $rootCategoryId = $categoryId ? ($rootCategoryIdByCategoryId[$categoryId] ?? null) : null;
        $warrantyPlans = $rootCategoryId ? ($warrantyPlansByRootCategory->get($rootCategoryId) ?? collect()) : collect();
        $warranty = $this->buildWarrantyBadge($warrantyPlans);

        $isFreeShipping = $isAdminListing
            ? ((int) ($listing->shipping_cost ?? 0)) === 0
            : (bool) $listing->vendor_covers_delivery;
        // TODO: no zone/rate is resolved in the cart context, so free shipping
        // here reflects vendor_covers_delivery / a flat admin shipping_cost of
        // zero only. A full ShippingCalculationService::calculate() call needs
        // a destination ShippingZone which isn't available at this layer.

        $vendorName = $isAdminListing ? null : ($listing->vendor?->store_name ?? $listing->vendor?->name);

        return [
            'id' => $item->id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unit_price,
            'line_total' => (int) $item->unit_price * (int) $item->quantity,
            'currency' => $isAdminListing ? $listing->currency : $listing->currency,
            'added_at' => optional($item->added_at)->toIso8601String(),
            'listing' => [
                'id' => $listing->id,
                'price' => (int) $listing->getRawOriginal('price'),
                'compare_at_price' => $isAdminListing ? null : ($listing->compare_at_price !== null ? (int) $listing->compare_at_price : null),
                'currency' => $listing->currency,
                'condition' => $isAdminListing ? null : $listing->condition,
                'global_system_type' => $isAdminListing ? null : $listing->global_system_type?->value,
                'vendor_covers_delivery' => $isAdminListing ? null : (bool) $listing->vendor_covers_delivery,
                'status' => $isAdminListing ? $listing->status?->value : $listing->status?->value,
                'flash_sale' => $flashSale,
                'vendor' => $isAdminListing ? null : ($listing->vendor ? [
                    'id' => $listing->vendor->id,
                    'name' => $vendorName,
                ] : null),
                'product_variant' => $variant ? [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name_en' => $product?->name_en,
                    'name_ar' => $product?->name_ar,
                    'primary_image_url' => $this->primaryImageUrl($variant),
                    'attributes' => $variant->attributeValues->map(fn ($value) => [
                        'attribute_en' => $value->attribute?->name_en,
                        'value_en' => $value->value_en,
                    ])->values()->all(),
                ] : null,
                'product' => $product ? [
                    'id' => $product->id,
                    'name_en' => $product->name_en,
                    'name_ar' => $product->name_ar,
                    'slug' => $product->slug,
                ] : null,
            ],
            'delivery' => [
                'primary_method' => $shippingMethod ? [
                    'id' => $shippingMethod->id,
                    'code' => $shippingMethod->code,
                    'badge_label_en' => $shippingMethod->badge_label_en,
                    'badge_label_ar' => $shippingMethod->badge_label_ar,
                    'badge_color_hex' => $shippingMethod->badge_color_hex,
                    'badge_text_color_hex' => $shippingMethod->badge_text_color_hex,
                    'is_express_type' => (bool) $shippingMethod->is_express_type,
                ] : null,
                'estimate' => $estimate,
                'available_methods' => $availableMethods,
                'is_free_shipping' => $isFreeShipping,
                'free_shipping_label_en' => $isFreeShipping ? 'Free Shipping' : null,
            ],
            'badges' => [
                'coupons' => $coupons,
                'low_stock' => [
                    'is_low_stock' => $stock['is_low_stock'],
                    'label_en' => $stock['low_stock_label_en'],
                    'label_ar' => $stock['low_stock_label_ar'],
                ],
                'warranty' => $warranty,
                'free_shipping' => $isFreeShipping,
                'sold_by' => $isAdminListing ? 'Official Store' : ($vendorName ? "Sold by {$vendorName}" : null),
            ],
            'stock' => [
                'available_quantity' => $stock['available_quantity'],
                'is_low_stock' => $stock['is_low_stock'],
                'is_out_of_stock' => $stock['is_out_of_stock'],
                'exceeds_stock' => $stock['exceeds_stock'],
            ],
            '_display_priority' => $shippingMethod->display_priority ?? null,
        ];
    }

    private function emptyItemShape($item): array
    {
        return [
            'id' => $item->id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unit_price,
            'line_total' => (int) $item->unit_price * (int) $item->quantity,
            'currency' => null,
            'added_at' => optional($item->added_at)->toIso8601String(),
            'listing' => null,
            'delivery' => [
                'primary_method' => null,
                'estimate' => null,
                'available_methods' => [],
                'is_free_shipping' => false,
                'free_shipping_label_en' => null,
            ],
            'badges' => [
                'coupons' => [],
                'low_stock' => ['is_low_stock' => false, 'label_en' => null, 'label_ar' => null],
                'warranty' => ['has_warranty_plan' => false, 'label_en' => null, 'label_ar' => null, 'plans' => []],
                'free_shipping' => false,
                'sold_by' => null,
            ],
            'stock' => [
                'available_quantity' => null,
                'is_low_stock' => false,
                'is_out_of_stock' => false,
                'exceeds_stock' => false,
            ],
            '_display_priority' => null,
        ];
    }

    /**
     * Resolves the effective shipping method for a cart item, in priority
     * order: (1) the customer's per-item selection, (2) the listing's
     * cached primary method, (3) the category's default method (filtered
     * by fbn/fbp availability for the listing type), (4) any available
     * method for the category, lowest display_priority first, (5) null —
     * the item is unresolvable and goes into the "unassigned" group.
     */
    private function resolvePrimaryShippingMethod(
        $item,
        $listing,
        bool $isAdminListing,
        Collection $shippingMethods,
        Collection $categoryMethodRows,
    ): ?ShippingMethod {
        if ($item->selected_shipping_method_id && $shippingMethods->has($item->selected_shipping_method_id)) {
            return $shippingMethods->get($item->selected_shipping_method_id);
        }

        if (!$isAdminListing && $listing->primary_shipping_method_id && $shippingMethods->has($listing->primary_shipping_method_id)) {
            return $shippingMethods->get($listing->primary_shipping_method_id);
        }

        $availableRows = $categoryMethodRows->filter(
            fn ($row) => $isAdminListing || $this->isRowAvailableForListing($row, $listing)
        );

        $defaultRow = $availableRows->first(fn ($row) => (bool) $row->is_default);
        if ($defaultRow && $shippingMethods->has($defaultRow->shipping_method_id)) {
            return $shippingMethods->get($defaultRow->shipping_method_id);
        }

        $bestRow = $availableRows
            ->filter(fn ($row) => $shippingMethods->has($row->shipping_method_id))
            ->sortBy(fn ($row) => $shippingMethods->get($row->shipping_method_id)->display_priority)
            ->first();

        return $bestRow ? $shippingMethods->get($bestRow->shipping_method_id) : null;
    }

    private function isRowAvailableForListing($row, $listing): bool
    {
        return $listing->global_system_type === GlobalSystemType::ExpressFbn
            ? (bool) $row->is_available_for_express_fbn
            : (bool) $row->is_available_for_merchant_fbp;
    }

    /**
     * Builds the full list of shipping methods available for this item's
     * category so the customer can switch their selection from the cart.
     * Fees are resolved from the pre-loaded per-zone rates; when no
     * destination zone could be resolved, fee is null (never 0) and a note
     * is attached instead.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAvailableMethods(
        $listing,
        bool $isAdminListing,
        Collection $categoryMethodRows,
        Collection $shippingMethods,
        ?ShippingMethod $selectedMethod,
        Collection $ratesByMethodId,
        ?string $destinationZoneId,
        string $timezone,
    ): array {
        return $categoryMethodRows
            ->filter(fn ($row) => $isAdminListing || $this->isRowAvailableForListing($row, $listing))
            ->filter(fn ($row) => $shippingMethods->has($row->shipping_method_id))
            ->map(function ($row) use ($shippingMethods, $selectedMethod, $ratesByMethodId, $destinationZoneId, $listing, $timezone) {
                $method = $shippingMethods->get($row->shipping_method_id);

                $fee = null;
                $isFree = false;
                $feeNote = null;

                if ($destinationZoneId === null) {
                    $feeNote = 'Add your address to see delivery cost';
                } else {
                    $rate = $ratesByMethodId->get($method->id);
                    if ($rate) {
                        $fee = (int) $rate->base_fee;
                        if (!empty($listing->vendor_covers_delivery)) {
                            $fee = 0;
                            $isFree = true;
                        }
                    }
                }

                return [
                    'id' => $method->id,
                    'code' => $method->code,
                    'badge_label_en' => $method->badge_label_en,
                    'badge_label_ar' => $method->badge_label_ar,
                    'badge_color_hex' => $method->badge_color_hex,
                    'badge_text_color_hex' => $method->badge_text_color_hex,
                    'is_express_type' => (bool) $method->is_express_type,
                    'display_priority' => $method->display_priority,
                    'is_default' => (bool) $row->is_default,
                    'is_selected' => $selectedMethod && $selectedMethod->id === $method->id,
                    'fee' => $fee,
                    'is_free_shipping' => $isFree,
                    'fee_note_en' => $feeNote,
                    'estimate' => $this->computeDeliveryEstimate($method, $timezone),
                ];
            })
            ->sortBy('display_priority')
            ->values()
            ->all();
    }

    /**
     * @return array{day_label_en:string,day_label_ar:?string,earliest_date:string,latest_date:string,countdown_label:?string,cutoff_passed:bool,express_upsell:?array}
     */
    public function computeDeliveryEstimate(
        ShippingMethod $method,
        string $timezone,
        ?Collection $shippingMethods = null,
        ?string $categoryId = null,
        ?Collection $categoryDefaultMethodId = null,
    ): array {
        $now = Carbon::now($timezone);
        $cutoffPassed = false;

        if ($method->order_cutoff_time) {
            $cutoff = Carbon::parse($now->toDateString() . ' ' . $method->order_cutoff_time, $timezone);
            $cutoffPassed = $now->greaterThan($cutoff);
        } else {
            $cutoff = null;
        }

        $minDays = (int) ($method->min_delivery_days ?? 0) + ($cutoffPassed ? 1 : 0);
        $maxDays = (int) ($method->max_delivery_days ?? $minDays) + ($cutoffPassed ? 1 : 0);

        $readyAt = $now->copy()->addHours((int) ($method->handling_time_hours ?? 0));
        $earliestAt = $readyAt->copy()->addDays($minDays);
        $latestAt = $readyAt->copy()->addDays($maxDays);

        $dayLabelEn = 'Today';
        $dayLabelAr = 'اليوم';
        if ($earliestAt->isTomorrow()) {
            $dayLabelEn = 'Tomorrow';
            $dayLabelAr = 'غداً';
        } elseif (!$earliestAt->isToday()) {
            $dayLabelEn = $earliestAt->format('D, M j');
            $dayLabelAr = null;
        }

        $countdownLabel = null;
        if (!$cutoffPassed && $cutoff) {
            $minutesLeft = max(0, $now->diffInMinutes($cutoff, false));
            $hrs = intdiv($minutesLeft, 60);
            $mins = $minutesLeft % 60;
            $countdownLabel = "Order in {$hrs} hrs {$mins} mins";
        }

        $expressUpsell = null;
        if (!$method->is_express_type && !$cutoffPassed && $shippingMethods && $categoryId && $categoryDefaultMethodId) {
            $expressAlternative = $shippingMethods->first(fn ($candidate) => $candidate->is_express_type && $candidate->id !== $method->id);
            if ($expressAlternative) {
                $expressUpsell = [
                    'message_en' => "Order in the next {$countdownLabel} for faster delivery with {$expressAlternative->badge_label_en}",
                    'message_ar' => null,
                ];
            }
        }

        return [
            'day_label_en' => $dayLabelEn,
            'day_label_ar' => $dayLabelAr,
            'earliest_date' => $earliestAt->toDateString(),
            'latest_date' => $latestAt->toDateString(),
            'countdown_label' => $countdownLabel,
            'cutoff_passed' => $cutoffPassed,
            'express_upsell' => $expressUpsell,
        ];
    }

    private function buildFlashSaleShape(?FlashSaleSubmission $submission): ?array
    {
        if (!$submission) {
            return null;
        }

        return [
            'active' => true,
            'flash_price' => (int) $submission->flash_price,
            'original_price' => (int) $submission->original_price,
            'discount_pct' => (float) $submission->calculated_discount_pct,
            'ends_at' => optional($submission->flashSale?->sale_ends_at)->toIso8601String(),
            'max_quantity_per_customer' => $submission->max_quantity_per_customer,
            'quantity_remaining' => (int) $submission->quantity_remaining,
        ];
    }

    /**
     * @return array{id:string,code:string,title_en:?string,title_ar:?string,type:string,value:mixed,label_en:string,label_ar:?string}
     */
    private function eligibleCouponsForItem($listing, Collection $activeCoupons, Collection $usedCouponCounts, ?string $categoryId): array
    {
        return $activeCoupons->filter(function (Coupon $coupon) use ($listing, $usedCouponCounts, $categoryId) {
            $usedCount = $usedCouponCounts->get($coupon->id, 0);
            if ($usedCount >= $coupon->usage_limit_per_customer) {
                return false;
            }

            return match ($coupon->scope) {
                CouponScope::Platform => true,
                CouponScope::Vendor => $coupon->vendor_id === $listing->vendor_id,
                CouponScope::Category => $categoryId !== null && $coupon->category_id === $categoryId,
                CouponScope::Product => false,
                default => false,
            };
        })->map(fn (Coupon $coupon) => [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'title_en' => $coupon->title_en,
            'title_ar' => $coupon->title_ar,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'label_en' => $this->couponLabel($coupon),
            'label_ar' => null,
        ])->values()->all();
    }

    private function couponLabel(Coupon $coupon): string
    {
        $value = rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.');

        return match ($coupon->type) {
            CouponType::Percentage => "Extra {$value}% off",
            CouponType::FixedAmount => "Extra {$value} " . ($coupon->currency ?? '') . ' off',
            CouponType::FreeShipping => 'Free Shipping',
            CouponType::Bogo => 'Buy One Get One',
            default => $coupon->title_en ?? $coupon->name,
        };
    }

    /**
     * @return array{available_quantity:?int,is_low_stock:bool,is_out_of_stock:bool,exceeds_stock:bool,low_stock_label_en:?string,low_stock_label_ar:?string}
     */
    private function stockStatus($listing, bool $isAdminListing, ?int $available, int $cartQty): array
    {
        if ($isAdminListing || $available === null) {
            return [
                'available_quantity' => null,
                'is_low_stock' => false,
                'is_out_of_stock' => false,
                'exceeds_stock' => false,
                'low_stock_label_en' => null,
                'low_stock_label_ar' => null,
            ];
        }

        $available = max(0, $available);
        $threshold = (int) ($listing->low_stock_threshold ?? 0);
        $isLowStock = $available > 0 && $available <= $threshold;
        $isOutOfStock = $available === 0;
        $exceedsStock = $cartQty > $available;

        return [
            'available_quantity' => $available,
            'is_low_stock' => $isLowStock,
            'is_out_of_stock' => $isOutOfStock,
            'exceeds_stock' => $exceedsStock,
            'low_stock_label_en' => $isLowStock ? "Only {$available} left in stock" : null,
            'low_stock_label_ar' => null,
        ];
    }

    /**
     * @return array{has_warranty_plan:bool,label_en:?string,label_ar:?string,plans:array}
     */
    private function buildWarrantyBadge(Collection $plans): array
    {
        if ($plans->isEmpty()) {
            return [
                'has_warranty_plan' => false,
                'label_en' => null,
                'label_ar' => null,
                'plans' => [],
            ];
        }

        $shortestDuration = $plans->min('duration_months');
        $label = $shortestDuration >= 12
            ? intdiv($shortestDuration, 12) . ' year warranty'
            : $shortestDuration . ' month warranty';

        return [
            'has_warranty_plan' => true,
            'label_en' => $label,
            'label_ar' => null,
            'plans' => $plans->map(fn (WarrantyPlan $plan) => [
                'id' => $plan->id,
                'name_en' => $plan->name_en,
                'name_ar' => $plan->name_ar,
                'duration_months' => $plan->duration_months,
                'price' => (int) $plan->price,
                'currency' => $plan->currency,
            ])->values()->all(),
        ];
    }

    private function resolveRootCategoryId(?string $categoryId, Collection $parentMap): ?string
    {
        if (!$categoryId) {
            return null;
        }

        $current = $categoryId;
        $visited = [];

        while (true) {
            if (isset($visited[$current])) {
                // Cyclic parent_id chain — bail out rather than loop forever.
                return $current;
            }
            $visited[$current] = true;

            $parent = $parentMap->get($current);
            if (!$parent) {
                return $current;
            }

            $current = $parent;
        }
    }

    private function primaryImageUrl($variant): ?string
    {
        $image = $variant->images->firstWhere('is_primary', true) ?? $variant->images->first();

        return $image?->url;
    }
}
