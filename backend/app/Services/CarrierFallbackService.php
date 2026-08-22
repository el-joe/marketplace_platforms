<?php

namespace App\Services;

use App\Models\City;
use App\Models\ShippingCompany;
use App\Models\ShippingFallbackRule;

class CarrierFallbackService
{
    public function resolveCarrierForCity(City $city): ?ShippingCompany
    {
        // Check if any active company explicitly serves this city
        $primary = ShippingCompany::where('status', 'active')
            ->whereJsonContains('served_cities', $city->id)
            ->first();

        if ($primary) {
            return $primary;
        }

        // Fall back to configured fallback rules ordered by priority (lower = first)
        $fallback = ShippingFallbackRule::where('unserved_city_id', $city->id)
            ->orderBy('priority')
            ->first();

        return $fallback?->fallbackShippingCompany;
    }
}
