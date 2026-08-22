<?php

namespace App\Services\Shipping;

use App\Models\Admin;
use App\Models\City;
use App\Models\ShippingZone;
use App\Services\ActivityLoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingZoneService
{
    public function __construct(
        private readonly ActivityLoggerService $logger
    ) {}

    public function createZone(array $data, Admin $admin): ShippingZone
    {
        return DB::transaction(function () use ($data, $admin) {
            $zone = ShippingZone::create($data);

            $this->logger->log(
                description: "Shipping zone created: {$zone->name}",
                subject: $zone,
                causer: $admin,
                logName: 'shipping',
                event: 'created'
            );

            return $zone;
        });
    }

    public function updateZone(ShippingZone $zone, array $data, Admin $admin): ShippingZone
    {
        $zone->update($data);

        $this->logger->log(
            description: "Shipping zone updated: {$zone->name}",
            subject: $zone,
            causer: $admin,
            logName: 'shipping',
            event: 'updated'
        );

        return $zone->fresh();
    }

    public function deleteZone(ShippingZone $zone): void
    {
        $activeRates = $zone->destinationRates()->where('is_active', 1)->get(['id', 'shipping_method_id']);

        if ($activeRates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'zone' => [
                    'Cannot delete zone: it has ' . $activeRates->count() . ' active shipping rate(s). '
                    . 'Deactivate or delete those rates first.',
                ],
            ]);
        }

        // Unassign cities
        City::where('shipping_zone_id', $zone->id)->update(['shipping_zone_id' => null]);

        $zone->delete();
    }

    public function assignCities(ShippingZone $zone, array $cityIds): array
    {
        return DB::transaction(function () use ($zone, $cityIds) {
            // Count previously assigned
            $previouslyAssigned = City::where('shipping_zone_id', $zone->id)->count();

            // Unassign cities that are in this zone but NOT in the new list
            $unassignedCount = City::where('shipping_zone_id', $zone->id)
                ->whereNotIn('id', $cityIds)
                ->count();

            City::where('shipping_zone_id', $zone->id)
                ->whereNotIn('id', $cityIds)
                ->update(['shipping_zone_id' => null]);

            // Assign new cities (only those belonging to the same country)
            City::whereIn('id', $cityIds)
                ->where('country_id', $zone->country_id)
                ->update(['shipping_zone_id' => $zone->id]);

            return [
                'assigned'   => count($cityIds),
                'unassigned' => $unassignedCount,
            ];
        });
    }

    public function unassignCity(City $city): void
    {
        $city->update(['shipping_zone_id' => null]);
    }

    public function getZoneWithCities(string $zoneId): ShippingZone
    {
        return ShippingZone::with(['cities' => fn ($q) => $q->orderBy('name_en')])
            ->findOrFail($zoneId);
    }

    public function duplicateZone(ShippingZone $zone, string $newName, Admin $admin): ShippingZone
    {
        return DB::transaction(function () use ($zone, $newName, $admin) {
            $newZone = ShippingZone::create([
                'name'        => $newName,
                'country_id'  => $zone->country_id,
                'description' => $zone->description,
                'is_active'   => false,
            ]);

            foreach ($zone->destinationRates as $rate) {
                $attrs = $rate->only([
                    'origin_zone_id',
                    'shipping_method_id',
                    'carrier_id',
                    'base_fee',
                    'rate_per_kg',
                    'min_weight_grams',
                    'volumetric_divisor',
                    'free_shipping_threshold',
                    'cod_extra_fee',
                ]);
                $attrs['destination_zone_id'] = $newZone->id;
                $attrs['is_active']           = false;

                \App\Models\ShippingRate::create($attrs);
            }

            $this->logger->log(
                description: "Shipping zone duplicated: {$zone->name} → {$newName}",
                subject: $newZone,
                causer: $admin,
                logName: 'shipping',
                event: 'created'
            );

            return $newZone;
        });
    }
}
