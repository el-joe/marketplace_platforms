<?php

namespace App\Services;

use App\Models\AdminListing;
use App\Models\CountryShippingSetting;
use App\Models\MarketplaceShippingRule;
use App\Models\PlatformShippingSubsidy;
use App\Models\ShippingRate;
use App\Models\ShippingWeightSlab;
use App\Models\VendorCityShippingSurcharge;
use App\Models\VendorListing;
use App\Models\WarehouseShippingSurcharge;

/**
 * Computes the per-sub-order shipping fee breakdown: what the customer is
 * charged, what the carrier actually costs the platform, and — for
 * "exceptional" lanes (carrier_rate > base_fee) — how the resulting gap is
 * split between the vendor and the platform subsidy.
 *
 * MONETARY INVARIANT: every money value in and out of this class is a BIGINT
 * base-currency unit. No value is ever multiplied or divided by 100. Several
 * legacy columns still carry a "_cents" suffix (warehouse_shipping_surcharges
 * .extra_amount_cents, vendor_city_shipping_surcharges.extra_amount_cents)
 * but store base-currency-unit integers like every other money column in
 * this schema — the suffix is a naming artifact from a rename sweep that
 * missed these two tables, not a unit difference. Do not scale them.
 *
 * SCHEMA NOTE: this class was written against the schema as it actually
 * exists, which differs from a couple of assumptions that are easy to make
 * from the table names alone:
 *  - shipping_weight_slabs' extra fee column is `extra_fee` (not
 *    `extra_fee_cents` — that column was renamed by a later migration; the
 *    ShippingWeightSlab model's $fillable/$casts were never updated to
 *    match and still reference the old name).
 *  - warehouse_shipping_surcharges has one row per warehouse
 *    (warehouse_id is unique) with no shipping_zone_id/shipping_method_id
 *    scoping — it is a flat per-warehouse surcharge, not a per-lane one.
 *  - vendor_city_shipping_surcharges no longer has a city_id column; it was
 *    dropped in favor of warehouse_id, so this is effectively a
 *    vendor+warehouse surcharge despite the table name.
 * `destination_city_id` is accepted as an input for forward-compatibility
 * and potential fallback-routing use, but nothing in the current schema
 * keys shipping cost off it, so it is not used in this calculation.
 */
class ShippingFeeCalculator
{
    /**
     * @param  array{
     *     listing: VendorListing|AdminListing,
     *     destination_zone_id: string,
     *     shipping_method_id: string,
     *     payment_method: string,
     *     destination_city_id: string|null,
     *     country_id: string,
     *     order_subtotal: int,
     *     preferred_carrier_id?: string|null,
     * } $params
     * @return array{
     *     fee: int,
     *     cod_fee: int,
     *     billable_weight_grams: int|null,
     *     carrier_shipping_cost: int,
     *     shipping_gap: int,
     *     admin_subsidy_amount: int,
     *     vendor_contribution: int,
     *     exceptional_subsidy_id: string|null,
     *     is_exceptional: bool,
     * }|null Null means this shipping method is not available on this lane.
     */
    public function calculate(array $params): ?array
    {
        $listing = $params['listing'];

        // ── STEP 1: admin-owned inventory has a flat, pre-set shipping cost
        //            and never goes through rate/subsidy resolution. ─────────
        if ($listing instanceof AdminListing) {
            return [
                'fee' => $listing->shipping_cost,
                'cod_fee' => 0,
                'billable_weight_grams' => null,
                'carrier_shipping_cost' => $listing->shipping_cost,
                'shipping_gap' => 0,
                'admin_subsidy_amount' => 0,
                'vendor_contribution' => 0,
                'exceptional_subsidy_id' => null,
                'is_exceptional' => false,
            ];
        }

        $destinationZoneId = $params['destination_zone_id'];
        $shippingMethodId = $params['shipping_method_id'];
        $isCod = $params['payment_method'] === 'cod';
        $countryId = $params['country_id'];
        $orderSubtotal = $params['order_subtotal'];
        $preferredCarrierId = $params['preferred_carrier_id'] ?? null;

        // ── STEP 2: rate lookup — prefer a rate scoped to the vendor's
        //            preferred carrier over the platform-default (NULL
        //            carrier_id) rate. ─────────────────────────────────────
        $rateQuery = ShippingRate::where('destination_zone_id', $destinationZoneId)
            ->where('shipping_method_id', $shippingMethodId)
            ->where('is_active', true);

        if ($preferredCarrierId) {
            $rateQuery->orderByRaw(
                'CASE WHEN carrier_id = ? THEN 0 WHEN carrier_id IS NULL THEN 1 ELSE 2 END',
                [$preferredCarrierId]
            );
        } else {
            $rateQuery->orderByRaw('CASE WHEN carrier_id IS NULL THEN 0 ELSE 1 END');
        }

        $rate = $rateQuery->first();

        if (! $rate) {
            return null;
        }

        // ── STEP 3: billable weight — greater of vendor-declared weight and
        //            volumetric weight, using this rate's divisor (falls
        //            back to 5000 if the rate's own default was somehow
        //            NULL). ───────────────────────────────────────────────
        $divisor = $rate->volumetric_divisor ?: 5000;

        $length = (int) $listing->declared_length_cm;
        $width = (int) $listing->declared_width_cm;
        $height = (int) $listing->declared_height_cm;

        $volumetricWeightGrams = 0;
        if ($length && $width && $height) {
            // Standard volumetric formula yields kg for cm dimensions with a
            // divisor around 5000; convert to grams without floats by doing
            // the *1000 before the final division.
            $volumetricWeightGrams = intdiv($length * $width * $height * 1000, $divisor);
        }

        $declaredWeightGrams = $listing->declared_weight_grams ?? 0;
        $billableWeightGrams = max($declaredWeightGrams, $volumetricWeightGrams);

        // ── STEP 4: base fee + weight-over-threshold surcharge. All per-kg
        //            math is done as (grams * rate_per_kg) / 1000, floored,
        //            to avoid floating point in money math. ────────────────
        $fee = $rate->base_fee;

        if ($billableWeightGrams > $rate->min_weight_grams) {
            $overGrams = $billableWeightGrams - $rate->min_weight_grams;
            $fee += intdiv($overGrams * $rate->rate_per_kg, 1000);
        }

        // ── STEP 5: weight slab surcharge (country + method specific band). ─
        $fee += $this->weightSlabFee($shippingMethodId, $countryId, $billableWeightGrams);

        // ── STEP 6: flat per-warehouse surcharge. ───────────────────────────
        if ($listing->warehouse_id) {
            $fee += (int) WarehouseShippingSurcharge::where('warehouse_id', $listing->warehouse_id)
                ->where('is_active', true)
                ->value('extra_amount_cents');
        }

        // ── STEP 7: vendor+warehouse surcharge (e.g. a vendor whose pickup
        //            location adds cost for this specific warehouse lane). ──
        if ($listing->warehouse_id) {
            $fee += (int) VendorCityShippingSurcharge::where('vendor_id', $listing->vendor_id)
                ->where('warehouse_id', $listing->warehouse_id)
                ->where('is_active', true)
                ->value('extra_amount_cents');
        }

        // ── STEP 8: free shipping threshold — rate-level threshold takes
        //            precedence; fall back to the country/method setting. ──
        $freeShippingThreshold = $rate->free_shipping_threshold
            ?? CountryShippingSetting::where('country_id', $countryId)
                ->where('shipping_method_id', $shippingMethodId)
                ->where('is_active', true)
                ->value('free_shipping_threshold');

        if ($freeShippingThreshold !== null && $orderSubtotal >= $freeShippingThreshold) {
            $fee = 0;
        }

        // ── STEP 9: marketplace rule override — a flat surcharge for
        //            special-handling listings (refrigeration, special
        //            vehicle, etc). Not waived by the free shipping
        //            threshold: it reflects a real extra handling cost. ────
        $marketplaceRule = MarketplaceShippingRule::where('vendor_listing_id', $listing->id)->first();
        if ($marketplaceRule) {
            $fee += $marketplaceRule->extra_delivery_fee;
        }

        // ── STEP 10: COD surcharge, tracked separately from the shipping fee. ─
        $codFee = $isCod ? $rate->cod_extra_fee : 0;

        // ── STEP 11: carrier cost — what the platform actually pays. ────────
        $carrierCost = $rate->carrier_rate;
        if ($billableWeightGrams > $rate->min_weight_grams && $rate->carrier_rate_per_kg > 0) {
            $overGrams = $billableWeightGrams - $rate->min_weight_grams;
            $carrierCost += intdiv($overGrams * $rate->carrier_rate_per_kg, 1000);
        }

        $isExceptional = $rate->carrier_rate > $rate->base_fee;

        if (! $isExceptional) {
            return [
                'fee' => $fee,
                'cod_fee' => $codFee,
                'billable_weight_grams' => $billableWeightGrams,
                'carrier_shipping_cost' => $carrierCost,
                'shipping_gap' => 0,
                'admin_subsidy_amount' => 0,
                'vendor_contribution' => 0,
                'exceptional_subsidy_id' => null,
                'is_exceptional' => false,
            ];
        }

        // ── STEP 12: exceptional lane — split the gap between vendor and
        //            platform subsidy. ────────────────────────────────────
        $gap = max(0, $carrierCost - $fee);

        $subsidyQuery = PlatformShippingSubsidy::where('shipping_zone_id', $destinationZoneId)
            ->where('shipping_method_id', $shippingMethodId)
            ->where('is_active', true)
            ->where(function ($query) use ($listing) {
                $query->where('warehouse_id', $listing->warehouse_id)->orWhereNull('warehouse_id');
            })
            ->where(function ($query) use ($rate) {
                $query->where('carrier_id', $rate->carrier_id)->orWhereNull('carrier_id');
            });

        if ($listing->warehouse_id) {
            $subsidyQuery->orderByRaw('CASE WHEN warehouse_id = ? THEN 0 ELSE 1 END', [$listing->warehouse_id]);
        }
        if ($rate->carrier_id) {
            $subsidyQuery->orderByRaw('CASE WHEN carrier_id = ? THEN 0 ELSE 1 END', [$rate->carrier_id]);
        }

        $subsidyRule = $subsidyQuery->first();

        // A weight-capped subsidy rule that this parcel exceeds does not
        // apply, same as if no rule existed at all.
        if ($subsidyRule && $subsidyRule->max_subsidy_weight_grams !== null
            && $billableWeightGrams > $subsidyRule->max_subsidy_weight_grams) {
            $subsidyRule = null;
        }

        if (! $subsidyRule) {
            $vendorContribution = $gap;
            $adminSubsidy = 0;
            $subsidyRuleId = null;
        } else {
            if ($subsidyRule->split_type === 'percentage') {
                $vendorContribution = intdiv($gap * $subsidyRule->vendor_share_pct, 100);
                $adminSubsidy = $gap - $vendorContribution;
            } else {
                $vendorContribution = min($subsidyRule->vendor_fixed_amount, $gap);
                $adminSubsidy = min($subsidyRule->admin_fixed_amount, $gap - $vendorContribution);
                $residual = $gap - $vendorContribution - $adminSubsidy;
                $adminSubsidy += $residual;
            }

            // Admin exposure ceiling: whatever the cap won't cover falls
            // back onto the vendor.
            if ($subsidyRule->subsidy_cap > 0 && $adminSubsidy > $subsidyRule->subsidy_cap) {
                $excess = $adminSubsidy - $subsidyRule->subsidy_cap;
                $adminSubsidy = $subsidyRule->subsidy_cap;
                $vendorContribution += $excess;
            }

            // INVARIANT: vendor_contribution + admin_subsidy must always
            // equal the gap exactly (zero-sum, no rounding leakage). Any
            // drift lands on the admin side rather than under/over-charging
            // the vendor.
            $drift = $gap - ($vendorContribution + $adminSubsidy);
            if ($drift !== 0) {
                $adminSubsidy += $drift;
            }

            $subsidyRuleId = $subsidyRule->id;
        }

        // ── STEP 13: vendor_covers_delivery override — customer sees "Free
        //            Delivery"; the vendor absorbs the shipping fee itself
        //            on top of whatever gap share was already assigned. ────
        if ($listing->vendor_covers_delivery) {
            $vendorContribution += $fee;
            $fee = 0;
        }

        return [
            'fee' => $fee,
            'cod_fee' => $codFee,
            'billable_weight_grams' => $billableWeightGrams,
            'carrier_shipping_cost' => $carrierCost,
            'shipping_gap' => $gap,
            'admin_subsidy_amount' => $adminSubsidy,
            'vendor_contribution' => $vendorContribution,
            'exceptional_subsidy_id' => $subsidyRuleId,
            'is_exceptional' => true,
        ];
    }

    private function weightSlabFee(string $shippingMethodId, string $countryId, int $billableWeightGrams): int
    {
        $extraFee = ShippingWeightSlab::where('shipping_method_id', $shippingMethodId)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->where('min_weight_grams', '<=', $billableWeightGrams)
            ->where(function ($query) use ($billableWeightGrams) {
                $query->whereNull('max_weight_grams')
                    ->orWhere('max_weight_grams', '>=', $billableWeightGrams);
            })
            ->value('extra_fee');

        return (int) ($extraFee ?? 0);
    }
}
