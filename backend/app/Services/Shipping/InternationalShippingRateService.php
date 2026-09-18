<?php

namespace App\Services\Shipping;

use App\Exceptions\InternationalShippingRateNotFoundException;
use App\Models\InternationalShippingRate;

/**
 * docs/plans/international_product_shipping.md Phase 2.
 *
 * Looks up the international_shipping_rates row for an origin/destination
 * country corridor and computes the shipping + customs fee. Deliberately does
 * NOT touch the existing domestic shipping_zones/shipping_rates machinery —
 * that's intra-country warehouse-dispatch routing only.
 */
class InternationalShippingRateService
{
    /**
     * @return array{
     *     rate_id: string,
     *     carrier_id: ?string,
     *     shipping_fee: int,
     *     customs_fee: int,
     *     total_fee: int,
     *     min_eta_days: int,
     *     max_eta_days: int,
     * }
     */
    public function quote(string $originCountryId, string $destinationCountryId, int $weightGrams): array
    {
        // Prefer a carrier-specific rate row over the generic (carrier_id
        // IS NULL) fallback for the same corridor. Ordering nulls last
        // achieves this without needing a specific carrier to match against.
        $rate = InternationalShippingRate::query()
            ->where('origin_country_id', $originCountryId)
            ->where('destination_country_id', $destinationCountryId)
            ->where('is_active', true)
            ->orderByRaw('carrier_id IS NULL')
            ->first();

        if (! $rate) {
            throw new InternationalShippingRateNotFoundException($originCountryId, $destinationCountryId);
        }

        // Round weight up to the next full kilogram, integer-only (no float
        // division): ceil(weightGrams / 1000) === intdiv(weightGrams + 999, 1000)
        // for weightGrams >= 0.
        $weightGrams = max(0, $weightGrams);
        $billableKg = intdiv($weightGrams + 999, 1000);

        $shippingFee = $rate->base_fee + ($rate->rate_per_kg * $billableKg);
        $customsFee = $rate->customs_fee_flat ?? 0;
        $totalFee = $shippingFee + $customsFee;

        return [
            'rate_id' => $rate->id,
            'carrier_id' => $rate->carrier_id,
            'shipping_fee' => $shippingFee,
            'customs_fee' => $customsFee,
            'total_fee' => $totalFee,
            'min_eta_days' => $rate->min_eta_days,
            'max_eta_days' => $rate->max_eta_days,
        ];
    }
}
