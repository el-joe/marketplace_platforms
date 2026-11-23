<?php

namespace App\Services\Shipping\DistancePricing;

use App\Enums\DistanceDeliveryStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ShippingDistancePricing;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\Checkout\CartLineSource;
use App\Support\Money;

/**
 * Applies a zone+method distance pricing rule to one cart shipping group (client item #19).
 *
 * Origin per seller: the warehouse that would fulfil the line (most available stock, the same pick as
 * checkout's reservation preview) → for a vendor, any of its seller-owned warehouses with coordinates →
 * the vendor's business address. Destination: the customer address pin. Each seller in the group is one
 * delivery leg, priced independently; the group fee is the sum of the legs.
 */
class DistancePricingResolver
{
    public const ORIGIN_FULFILLING_WAREHOUSE = 'fulfilling_warehouse';

    public const ORIGIN_VENDOR_WAREHOUSE = 'vendor_warehouse';

    public const ORIGIN_STORE_LOCATION = 'store_location';

    /** @var array<string, ?string> category id → parent id */
    private array $categoryParents = [];

    public function __construct(private readonly DistanceFeeCalculator $calculator) {}

    public function activeRuleFor(?string $zoneId, string $shippingMethodId): ?ShippingDistancePricing
    {
        if ($zoneId === null) {
            return null;
        }

        return ShippingDistancePricing::query()
            ->where('shipping_zone_id', $zoneId)
            ->where('shipping_method_id', $shippingMethodId)
            ->where('is_active', true)
            ->with(['tiers', 'categories:id'])
            ->first();
    }

    /**
     * Null when the rule's category scope does not cover the group.
     *
     * @param  array<int, CartItem>  $cartItems
     * @return array{status: string, available: bool, distance_km: ?float, fee: int|float, fee_by_seller: array<string, int|float>, free_radius_km: ?float, max_service_radius_km: ?float, tier: ?array, fallback_reason: ?string, message: string, snapshot: array<string, mixed>}|null
     */
    public function resolve(ShippingDistancePricing $pricing, Address $address, array $cartItems, bool $orderAmountThresholdMet): ?array
    {
        $sources = collect($cartItems)
            ->map(fn (CartItem $item) => CartLineSource::resolve($item))
            ->filter()
            ->values();

        if ($sources->isEmpty() || ! $this->scopeCovers($pricing, $sources->all())) {
            return null;
        }

        $rule = DistanceRule::fromModel($pricing);
        $destination = $this->coordinates($address->latitude, $address->longitude);

        $quotes = [];
        $origins = [];
        foreach ($sources->groupBy(fn (CartLineSource $source) => $source->sellerParty) as $sellerParty => $sellerSources) {
            $origins[$sellerParty] = $this->originFor($sellerSources->first());
            $distance = DistanceFeeCalculator::distanceBetween($origins[$sellerParty]['coordinates'] ?? null, $destination);
            $quotes[$sellerParty] = $this->calculator->quote($rule, $distance, $orderAmountThresholdMet);
        }

        $farthest = collect($quotes)->sortByDesc(fn (DistanceQuote $quote) => $quote->distanceKm ?? -1)->first();
        $status = $this->groupStatus($quotes);
        $isPriced = $status->isDistancePriced();

        $feeBySeller = $isPriced
            ? array_map(fn (DistanceQuote $quote) => Money::number($quote->fee), $quotes)
            : [];

        $payload = [
            'status' => $status->value,
            'available' => $status !== DistanceDeliveryStatus::Unavailable,
            'distance_km' => $farthest?->distanceKm,
            'fee' => Money::number(array_sum($feeBySeller)),
            'fee_by_seller' => $feeBySeller,
            'free_radius_km' => $rule->freeRadiusKm,
            'max_service_radius_km' => $rule->maxServiceRadiusKm,
            'tier' => $status === DistanceDeliveryStatus::Tier ? $farthest?->tier?->toArray() : null,
            'fallback_reason' => $status === DistanceDeliveryStatus::Fallback
                ? (collect($quotes)->first(fn (DistanceQuote $quote) => $quote->status === DistanceDeliveryStatus::Fallback)?->fallbackReason)
                : null,
        ];

        $payload['message'] = $this->message($payload);
        $payload['snapshot'] = $this->snapshot($pricing, $status, $quotes, $origins);

        return $payload;
    }

    /**
     * Audit record of the decision per seller leg, stored on the sub-order at place-order time. Internal only:
     * CheckoutCalculationService moves it out of the customer-facing payload.
     *
     * @param  array<string, DistanceQuote>  $quotes
     * @param  array<string, array{type: string, id: string|int|null, coordinates: array{0: float, 1: float}}|null>  $origins
     * @return array{rule_id: string, shipping_zone_id: string, shipping_method_id: string, group_status: string, applied: bool, free_radius_km: ?float, max_service_radius_km: ?float, legs: array<string, array<string, mixed>>}
     */
    private function snapshot(ShippingDistancePricing $pricing, DistanceDeliveryStatus $groupStatus, array $quotes, array $origins): array
    {
        $legs = [];
        foreach ($quotes as $sellerParty => $quote) {
            $legs[$sellerParty] = [
                'status' => $quote->status->value,
                'distance_km' => $quote->distanceKm,
                'fee' => $groupStatus->isDistancePriced() ? Money::number($quote->fee) : null,
                'tier' => $quote->tier?->toArray(),
                'fallback_reason' => $quote->fallbackReason,
                'origin_type' => $origins[$sellerParty]['type'] ?? null,
                'origin_id' => $origins[$sellerParty]['id'] ?? null,
            ];
        }

        return [
            'rule_id' => (string) $pricing->id,
            'shipping_zone_id' => (string) $pricing->shipping_zone_id,
            'shipping_method_id' => (string) $pricing->shipping_method_id,
            'group_status' => $groupStatus->value,
            'applied' => $groupStatus->isDistancePriced(),
            'free_radius_km' => $pricing->free_radius_km === null ? null : (float) $pricing->free_radius_km,
            'max_service_radius_km' => $pricing->max_service_radius_km === null ? null : (float) $pricing->max_service_radius_km,
            'legs' => $legs,
        ];
    }

    /**
     * @param  array<string, DistanceQuote>  $quotes
     */
    private function groupStatus(array $quotes): DistanceDeliveryStatus
    {
        $statuses = array_map(fn (DistanceQuote $quote) => $quote->status, $quotes);

        foreach ([DistanceDeliveryStatus::Unavailable, DistanceDeliveryStatus::Fallback, DistanceDeliveryStatus::FreeByOrderAmount, DistanceDeliveryStatus::Tier] as $dominant) {
            if (in_array($dominant, $statuses, true)) {
                return $dominant;
            }
        }

        return DistanceDeliveryStatus::FreeWithinRadius;
    }

    /**
     * @param  array{status: string, distance_km: ?float, fee: int|float, free_radius_km: ?float, max_service_radius_km: ?float}  $payload
     */
    private function message(array $payload): string
    {
        $distance = $payload['distance_km'] === null ? null : number_format($payload['distance_km'], 1);

        return match (DistanceDeliveryStatus::from($payload['status'])) {
            DistanceDeliveryStatus::Unavailable => __('distance_delivery.unavailable', ['distance' => $distance, 'max' => number_format((float) $payload['max_service_radius_km'], 1)]),
            DistanceDeliveryStatus::FreeByOrderAmount => __('distance_delivery.free_by_order_amount', ['distance' => $distance]),
            DistanceDeliveryStatus::FreeWithinRadius => __('distance_delivery.free_within_radius', ['radius' => number_format((float) $payload['free_radius_km'], 1), 'distance' => $distance]),
            DistanceDeliveryStatus::Tier => __('distance_delivery.tier', ['distance' => $distance, 'fee' => Money::format($payload['fee'], 3)]),
            DistanceDeliveryStatus::Fallback => '',
        };
    }

    /**
     * Rule without categories = every category. Otherwise every line's product category (or one of its
     * ancestors) must be in scope; a mixed group keeps normal zone pricing.
     *
     * @param  list<CartLineSource>  $sources
     */
    private function scopeCovers(ShippingDistancePricing $pricing, array $sources): bool
    {
        $scope = $pricing->categories->pluck('id')->flip();
        if ($scope->isEmpty()) {
            return true;
        }

        foreach ($sources as $source) {
            $categoryId = $source->sellable->productVariant?->product?->category_id
                ?? $source->fulfilmentListing->productVariant?->product?->category_id;

            if (! $this->categoryInScope($categoryId, $scope->all())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $scope
     */
    private function categoryInScope(?string $categoryId, array $scope): bool
    {
        for ($depth = 0; $categoryId !== null && $depth < 10; $depth++) {
            if (isset($scope[$categoryId])) {
                return true;
            }

            if (! array_key_exists($categoryId, $this->categoryParents)) {
                $this->categoryParents[$categoryId] = Category::whereKey($categoryId)->value('parent_id');
            }

            $categoryId = $this->categoryParents[$categoryId];
        }

        return false;
    }

    /**
     * @return array{type: string, id: string|int|null, coordinates: array{0: float, 1: float}}|null
     */
    private function originFor(CartLineSource $source): ?array
    {
        $inventory = $source->warehouseInventories
            ->sortByDesc(fn ($row) => (int) $row->quantity_on_hand - (int) $row->quantity_reserved)
            ->first();

        $warehouse = $inventory ? Warehouse::with('address')->find($inventory->warehouse_id) : null;
        if ($origin = $this->warehouseCoordinates($warehouse)) {
            return ['type' => self::ORIGIN_FULFILLING_WAREHOUSE, 'id' => $warehouse->id, 'coordinates' => $origin];
        }

        if ($source->isAdminSeller() || str_starts_with($source->sellerParty, 'marketer:')) {
            return null;
        }

        $ownWarehouses = Warehouse::with('address')
            ->where('owner_vendor_id', $source->sellerParty)
            ->where('is_active', true)
            ->get();
        foreach ($ownWarehouses as $ownWarehouse) {
            if ($origin = $this->warehouseCoordinates($ownWarehouse)) {
                return ['type' => self::ORIGIN_VENDOR_WAREHOUSE, 'id' => $ownWarehouse->id, 'coordinates' => $origin];
            }
        }

        $businessAddress = Vendor::with('businessAddress')->find($source->sellerParty)?->businessAddress;
        $origin = $businessAddress ? $this->coordinates($businessAddress->latitude, $businessAddress->longitude) : null;

        return $origin ? ['type' => self::ORIGIN_STORE_LOCATION, 'id' => $source->sellerParty, 'coordinates' => $origin] : null;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function warehouseCoordinates(?Warehouse $warehouse): ?array
    {
        if ($warehouse === null) {
            return null;
        }

        return $this->coordinates($warehouse->latitude, $warehouse->longitude)
            ?? ($warehouse->address ? $this->coordinates($warehouse->address->latitude, $warehouse->address->longitude) : null);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function coordinates(mixed $latitude, mixed $longitude): ?array
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        // 0,0 is the default of some legacy forms, never a real store/customer in this market.
        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return [$lat, $lng];
    }
}
