<?php

namespace App\Services;

use App\Models\PlatformShippingSubsidy;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * All weight values in GRAMS. All money values are BIGINT base currency
 * units - never divide or multiply by 100 here.
 */
class ShippingSubsidyService
{
    public const CACHE_VERSION_KEY = 'shipping_subsidy_cache_version';

    public function __construct(
        private readonly ShippingWeightService $weightService,
        private readonly ShippingCalculationService $shippingCalculationService,
    ) {}

    /**
     * Resolve the shipping fee breakdown for a single vendor's cart items
     * (sub-orders are grouped per vendor, so billable weight/fees are too).
     *
     * Returns:
     * [
     *   'raw_fee'                => int,  // full carrier fee before subsidy
     *   'subsidy_cap'            => int,  // platform max contribution
     *   'platform_subsidy'       => int,  // actual platform pays (min of cap vs raw)
     *   'vendor_contribution'    => int,  // vendor absorbs if vendor_covers_delivery=1
     *   'customer_pays'          => int,  // what customer is charged (0 if free)
     *   'is_free_for_customer'   => bool,
     *   'is_free_by_platform'    => bool,
     *   'is_free_by_vendor'      => bool,
     *   'billable_weight_grams'  => int,
     * ]
     *
     * @param  Collection<int, \App\Models\CartItem>  $vendorCartItems  all cart items belonging to one vendor
     */
    public function resolve(
        Collection $vendorCartItems,
        ShippingZone $zone,
        ShippingMethod $method
    ): array {
        if ($vendorCartItems->isEmpty()) {
            return $this->noShippingAvailable();
        }

        $billable = $vendorCartItems->sum(function ($item) {
            $listing = $item->vendorListing;
            $perUnit = $this->weightService->billableWeightGrams(
                (int) ($listing->declared_weight_grams ?? $listing->productVariant?->weight_grams ?? 0),
                $listing->declared_length_cm !== null ? (float) $listing->declared_length_cm : null,
                $listing->declared_width_cm !== null ? (float) $listing->declared_width_cm : null,
                $listing->declared_height_cm !== null ? (float) $listing->declared_height_cm : null,
            );

            return $perUnit * $item->quantity;
        });

        $rate = ShippingRate::where('destination_zone_id', $zone->id)
            ->where('shipping_method_id', $method->id)
            ->where('is_active', true)
            ->whereNull('origin_zone_id')
            ->orderBy('base_fee')
            ->first();

        if (! $rate) {
            return $this->noShippingAvailable($billable);
        }

        $rawFee = $this->weightService->computeRawShippingFee($rate, $billable);
        $rawFee += $this->shippingCalculationService->getWeightSlabFee($method->id, $zone->country_id, $billable);

        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        $subsidy = Cache::remember(
            "shipping_subsidy_v{$version}_{$zone->id}_{$method->id}",
            300,
            fn() => PlatformShippingSubsidy::where('shipping_zone_id', $zone->id)
                ->where('shipping_method_id', $method->id)
                ->where('is_active', true)
                ->first()
        );

        $subsidyCap = 0;
        if ($subsidy) {
            if ($subsidy->max_subsidy_weight_grams !== null && $billable > $subsidy->max_subsidy_weight_grams) {
                $subsidizableFee = $this->weightService->computeRawShippingFee($rate, $subsidy->max_subsidy_weight_grams);
                $subsidyCap = min($subsidy->subsidy_cap, $subsidizableFee);
            } else {
                $subsidyCap = min($subsidy->subsidy_cap, $rawFee);
            }
        }

        $customerWouldPay = max(0, $rawFee - $subsidyCap);

        $vendorCoversDelivery = (bool) $vendorCartItems->first()->vendorListing->vendor_covers_delivery;
        $vendorContribution = 0;
        if ($vendorCoversDelivery && $customerWouldPay > 0) {
            $vendorContribution = $customerWouldPay;
            $customerWouldPay = 0;
        }

        return [
            'raw_fee' => $rawFee,
            'subsidy_cap' => $subsidyCap,
            'platform_subsidy' => $subsidyCap,
            'vendor_contribution' => $vendorContribution,
            'customer_pays' => $customerWouldPay,
            'is_free_for_customer' => $customerWouldPay === 0,
            'is_free_by_platform' => $customerWouldPay === 0 && $subsidyCap >= $rawFee && $rawFee > 0,
            'is_free_by_vendor' => $customerWouldPay === 0 && $vendorContribution > 0,
            'billable_weight_grams' => $billable,
        ];
    }

    public static function flushCache(): void
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $version + 1);
    }

    private function noShippingAvailable(int $billableWeightGrams = 0): array
    {
        return [
            'raw_fee' => 0,
            'subsidy_cap' => 0,
            'platform_subsidy' => 0,
            'vendor_contribution' => 0,
            'customer_pays' => 0,
            'is_free_for_customer' => true,
            'is_free_by_platform' => false,
            'is_free_by_vendor' => false,
            'billable_weight_grams' => $billableWeightGrams,
        ];
    }
}
