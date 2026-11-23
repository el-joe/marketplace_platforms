<?php

namespace App\Services\Shipping\DistancePricing;

use App\Enums\VendorGlobalStatus;
use App\Enums\VendorType;
use App\Models\Admin;
use App\Models\ShippingDistancePricing;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Admin persistence for distance pricing rules (client item #19).
 */
class ShippingDistancePricingService
{
    public function __construct(private readonly ActivityLoggerService $logger) {}

    /**
     * @param  array{free_radius_km: float|string|null, max_service_radius_km: float|string|null, is_active: bool, tiers: list<array{from_km: mixed, to_km: mixed, price: mixed}>, category_ids: list<string>}  $data
     */
    public function save(ShippingZone $zone, ShippingMethod $method, array $data, Admin $admin): ShippingDistancePricing
    {
        $pricing = DB::transaction(function () use ($zone, $method, $data) {
            $pricing = ShippingDistancePricing::updateOrCreate(
                ['shipping_zone_id' => $zone->id, 'shipping_method_id' => $method->id],
                [
                    'free_radius_km' => $data['free_radius_km'],
                    'max_service_radius_km' => $data['max_service_radius_km'],
                    'is_active' => $data['is_active'],
                ],
            );

            $pricing->tiers()->delete();
            $pricing->tiers()->createMany(
                collect($data['tiers'])
                    ->sortBy(fn (array $tier) => (float) $tier['from_km'])
                    ->map(fn (array $tier) => [
                        'from_km' => round((float) $tier['from_km'], 1),
                        'to_km' => $tier['to_km'] === null ? null : round((float) $tier['to_km'], 1),
                        'price' => $tier['price'],
                    ])
                    ->values()
                    ->all(),
            );

            $pricing->categories()->sync($data['category_ids']);

            return $pricing;
        });

        $this->logger->log(
            description: "Distance pricing saved for zone {$zone->name} / {$method->name}",
            subject: $pricing,
            causer: $admin,
            logName: 'shipping',
            event: $pricing->wasRecentlyCreated ? 'created' : 'updated',
            properties: ['attributes' => $data],
        );

        return $pricing->load(['tiers', 'categories:id']);
    }

    public function delete(ShippingDistancePricing $pricing, Admin $admin): void
    {
        $pricing->delete();

        $this->logger->log(
            description: "Distance pricing removed: {$pricing->id}",
            subject: $pricing,
            causer: $admin,
            logName: 'shipping',
            event: 'deleted',
        );
    }

    /**
     * @return array{shipping_method_id: string, free_radius_km: ?float, max_service_radius_km: ?float, is_active: bool, tiers: list<array{from_km: float, to_km: ?float, price: int|float}>, category_ids: list<string>}
     */
    public function present(ShippingDistancePricing $pricing): array
    {
        return [
            'shipping_method_id' => $pricing->shipping_method_id,
            'free_radius_km' => $pricing->free_radius_km,
            'max_service_radius_km' => $pricing->max_service_radius_km,
            'is_active' => $pricing->is_active,
            'tiers' => $pricing->tiers->map(fn ($tier) => [
                'from_km' => $tier->from_km,
                'to_km' => $tier->to_km,
                'price' => $tier->price,
            ])->values()->all(),
            'category_ids' => $pricing->categories->pluck('id')->values()->all(),
        ];
    }

    /**
     * Admin hint on the distance pricing tab: active product stores and active warehouses of the zone's country
     * that have no usable coordinates (so their orders silently keep the normal zone rate), with fix links.
     *
     * @return array{vendors_total: int, vendors_missing: int, vendors: list<array{name: string, url: string}>, warehouses_total: int, warehouses_missing: int, warehouses: list<array{name: string, url: string}>}
     */
    public function coordinateCoverage(string $countryId, int $sampleSize = 10): array
    {
        $vendors = Vendor::query()
            ->where('country_id', $countryId)
            ->where('global_status', VendorGlobalStatus::Active->value)
            ->where('vendor_type', VendorType::ProductVendor->value);

        $vendorsMissing = (clone $vendors)
            ->whereDoesntHave('businessAddress', fn (Builder $address) => $this->withCoordinates($address))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('warehouses')
                ->whereColumn('warehouses.owner_vendor_id', 'vendors.id')
                ->where('warehouses.is_active', true)
                ->whereNotNull('warehouses.latitude')
                ->whereNotNull('warehouses.longitude')
                ->whereRaw('NOT (warehouses.latitude = 0 AND warehouses.longitude = 0)'));

        $warehouses = Warehouse::query()->where('country_id', $countryId)->where('is_active', true);

        $warehousesMissing = (clone $warehouses)
            ->where(fn (Builder $query) => $query->whereNull('latitude')->orWhereNull('longitude')->orWhereRaw('(latitude = 0 AND longitude = 0)'))
            ->whereDoesntHave('address', fn (Builder $address) => $this->withCoordinates($address));

        return [
            'vendors_total' => (clone $vendors)->count(),
            'vendors_missing' => (clone $vendorsMissing)->count(),
            'vendors' => $vendorsMissing->orderBy('store_name')->limit($sampleSize)->get(['id', 'store_name'])
                ->map(fn (Vendor $vendor) => ['name' => (string) $vendor->store_name, 'url' => route('admin.vendors.show', $vendor).'#store-location'])
                ->all(),
            'warehouses_total' => (clone $warehouses)->count(),
            'warehouses_missing' => (clone $warehousesMissing)->count(),
            'warehouses' => $warehousesMissing->orderBy('name')->limit($sampleSize)->get(['id', 'name'])
                ->map(fn (Warehouse $warehouse) => ['name' => (string) $warehouse->name, 'url' => route('admin.warehouses.edit', $warehouse)])
                ->all(),
        ];
    }

    /**
     * Address rows store coordinates as strings; '' and 0,0 are not a location (same rule as the resolver).
     */
    private function withCoordinates(Builder $address): Builder
    {
        return $address->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('latitude', '!=', '')->where('longitude', '!=', '')
            ->whereRaw('NOT (CAST(latitude AS DECIMAL(10,7)) = 0 AND CAST(longitude AS DECIMAL(10,7)) = 0)');
    }
}
