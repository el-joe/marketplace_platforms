<?php

namespace App\Services\Shipping;

use App\Models\Admin;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\ActivityLoggerService;

class ShippingRateService
{
    public function __construct(
        private readonly ActivityLoggerService $logger
    ) {}

    public function createRate(array $data, Admin $admin): ShippingRate
    {
        $data = $this->normalizeRateData($data);

        $rate = ShippingRate::create($data);

        $this->logger->log(
            description: "Shipping rate created for zone {$rate->destination_zone_id}",
            subject: $rate,
            causer: $admin,
            logName: 'shipping',
            event: 'created'
        );

        return $rate;
    }

    public function updateRate(ShippingRate $rate, array $data, Admin $admin): ShippingRate
    {
        $data = $this->normalizeRateData($data);
        $rate->update($data);

        $this->logger->log(
            description: "Shipping rate updated: {$rate->id}",
            subject: $rate,
            causer: $admin,
            logName: 'shipping',
            event: 'updated'
        );

        return $rate->fresh();
    }

    public function deleteRate(ShippingRate $rate): void
    {
        $rate->delete();
    }

    public function bulkDelete(array $rateIds): int
    {
        return ShippingRate::whereIn('id', $rateIds)->delete();
    }

    public function bulkToggle(array $rateIds, bool $active): int
    {
        return ShippingRate::whereIn('id', $rateIds)
            ->update(['is_active' => $active ? 1 : 0]);
    }

    public function duplicateRatesForZone(ShippingZone $sourceZone, ShippingZone $targetZone): int
    {
        $rates = ShippingRate::where('destination_zone_id', $sourceZone->id)->get();
        $count = 0;

        foreach ($rates as $rate) {
            ShippingRate::create(array_merge(
                $rate->only([
                    'origin_zone_id',
                    'shipping_method_id',
                    'carrier_id',
                    'base_fee',
                    'rate_per_kg',
                    'min_weight_grams',
                    'volumetric_divisor',
                    'free_shipping_threshold',
                    'cod_extra_fee',
                ]),
                [
                    'destination_zone_id' => $targetZone->id,
                    'is_active'           => false,
                ]
            ));
            $count++;
        }

        return $count;
    }

    public function calculateEstimate(
        string $zoneId,
        string $methodId,
        int $weightGrams,
        int $orderValueCents,
        bool $isCod
    ): array {
        $rate = ShippingRate::where('destination_zone_id', $zoneId)
            ->where('shipping_method_id', $methodId)
            ->where('is_active', 1)
            ->with(['shippingMethod', 'carrier'])
            ->first();

        if (! $rate) {
            return ['available' => false];
        }

        $isFree    = $rate->isFreeShipping($orderValueCents);
        $costCents = $isFree ? 0 : $rate->calculateCost($weightGrams, $isCod);

        return [
            'available'               => true,
            'is_free'                 => $isFree,
            'cost'              => $costCents,
            'cost_formatted'          => number_format($costCents, 2),
            'method_name'             => $rate->shippingMethod->name,
            'carrier_name'            => $rate->carrier?->name ?? 'Any',
            'delivery_days'           => $rate->shippingMethod->min_delivery_days
                                         . '-' . $rate->shippingMethod->max_delivery_days,
            'free_threshold_formatted' => $rate->free_threshold_formatted,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function normalizeRateData(array $data): array
    {
        if (isset($data['base_fee'])) {
            $data['base_fee'] = (int) $data['base_fee'];
        }
        if (isset($data['rate_per_kg'])) {
            $data['rate_per_kg'] = (int) $data['rate_per_kg'];
        }
        if (isset($data['carrier_rate']) && $data['carrier_rate'] !== null && $data['carrier_rate'] !== '') {
            $data['carrier_rate'] = (int) $data['carrier_rate'];
        } else {
            // carrier_rate is NOT NULL default 0 at the DB level; this column
            // drives the shipping gap calculation, so it must never be null.
            $data['carrier_rate'] = 0;
        }
        if (isset($data['carrier_rate_per_kg']) && $data['carrier_rate_per_kg'] !== null && $data['carrier_rate_per_kg'] !== '') {
            $data['carrier_rate_per_kg'] = (int) $data['carrier_rate_per_kg'];
        } else {
            $data['carrier_rate_per_kg'] = 0;
        }
        if (isset($data['cod_extra_fee'])) {
            $data['cod_extra_fee'] = (int) $data['cod_extra_fee'];
        }
        if (isset($data['free_shipping_threshold']) && $data['free_shipping_threshold'] !== null && $data['free_shipping_threshold'] !== '') {
            $data['free_shipping_threshold'] = (int) $data['free_shipping_threshold'];
        } else {
            $data['free_shipping_threshold'] = null;
        }
        return $data;
    }
}
