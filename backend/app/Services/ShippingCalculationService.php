<?php

namespace App\Services;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingWeightSlab;
use App\Models\ShippingZone;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\ValueObjects\ShippingBreakdown;

/**
 * All weight values are GRAMS. All money values are BIGINT base currency
 * units — never divide or multiply by 100 here.
 */
class ShippingCalculationService
{
    /**
     * Effective billable weight for a single unit: the greater of actual
     * weight and volumetric weight (L x W x H / divisor, in kg, converted to grams).
     */
    public function resolveEffectiveWeightGrams(
        int $lengthCm,
        int $widthCm,
        int $heightCm,
        int $actualWeightGrams,
        int $volumetricDivisor = 5000,
    ): int {
        $volumetricWeightGrams = (int) round((($lengthCm * $widthCm * $heightCm) / $volumetricDivisor) * 1000);

        return max($actualWeightGrams, $volumetricWeightGrams);
    }

    public function getWeightSlabFee(string $shippingMethodId, string $countryId, int $effectiveWeightGrams): int
    {
        $extraFee = ShippingWeightSlab::where('shipping_method_id', $shippingMethodId)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->where('min_weight_grams', '<=', $effectiveWeightGrams)
            ->where(function ($query) use ($effectiveWeightGrams) {
                $query->whereNull('max_weight_grams')
                    ->orWhere('max_weight_grams', '>=', $effectiveWeightGrams);
            })
            ->value('extra_fee');

        return (int) ($extraFee ?? 0);
    }

    /**
     * Calculate the customer-facing shipping fee for one sub_order.
     * Exceptional-zone gap handling lives separately in
     * ExceptionalZoneService — this method never touches carrier cost.
     */
    public function calculate(
        Vendor $vendor,
        VendorListing $listing,
        int $quantity,
        ShippingZone $destinationZone,
        ShippingMethod $method,
        int $billableWeightGrams,
    ): ShippingBreakdown {
        // ── STEP 1: Resolve the warehouse this listing ships from, and its
        //            origin zone (used to prefer a lane-specific rate/rule). ──
        $warehouse = $listing->warehouse_id ? $listing->warehouse()->first() : null;
        $originZoneId = $warehouse?->shipping_zone_id;

        // ── STEP 2: Find the shipping rate for this zone × method, preferring
        //            one scoped to this warehouse's origin zone over a
        //            zone-agnostic (NULL origin_zone_id) fallback rate. ──────
        $rateQuery = ShippingRate::where('destination_zone_id', $destinationZone->id)
            ->where('shipping_method_id', $method->id)
            ->where('is_active', 1);

        if ($originZoneId) {
            $rateQuery->orderByRaw('CASE WHEN origin_zone_id = ? THEN 0 ELSE 1 END', [$originZoneId]);
        } else {
            $rateQuery->orderByRaw('CASE WHEN origin_zone_id IS NULL THEN 0 ELSE 1 END');
        }

        $rate = $rateQuery->first();

        if (! $rate) {
            return ShippingBreakdown::notServiced();
        }

        // ── STEP 3: Calculate CUSTOMER rate (what they see at checkout) ─────────
        $customerShipping = $rate->base_fee;
        if ($billableWeightGrams > $rate->min_weight_grams) {
            $overKg = ($billableWeightGrams - $rate->min_weight_grams) / 1000;
            $customerShipping += (int) round($overKg * $rate->rate_per_kg);
        }

        $slabFee = $this->getWeightSlabFee($method->id, $destinationZone->country_id, $billableWeightGrams);
        $customerShipping += $slabFee;

        return new ShippingBreakdown(
            customerPays: $customerShipping,
            carrierCost: $customerShipping,
            gap: 0,
            vendorContribution: 0,
            adminSubsidy: 0,
            subsidyRuleId: null,
            isExceptional: false,
            billableWeightGrams: $billableWeightGrams,
        );
    }
}
