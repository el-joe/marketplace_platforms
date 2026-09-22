<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Checkout\CartLineSource;
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
        private readonly ShippingFeeCalculator $feeCalculator,
    ) {}

    /**
     * Resolve the shipping fee breakdown for a single vendor's cart items
     * (sub-orders are grouped per vendor, so billable weight/fees are too).
     *
     * This is a checkout-time PREVIEW of the fee that
     * ShippingFeeCalculator::calculate() will persist to sub_orders.shipping
     * at order placement. It must never run its own parallel fee/subsidy
     * formula — it delegates to ShippingFeeCalculator::calculate() (once per
     * cart line, since that is the granularity Calculator operates at) and
     * sums the results, so the preview always matches what actually gets
     * charged.
     *
     * Returns:
     * [
     *   'raw_fee'                => int,  // carrier's own cost (Calculator's carrier_shipping_cost) - what the platform is quoted, independent of what the customer is charged
     *   'subsidy_cap'            => int,  // platform's actual contribution to the carrier-cost gap (same as platform_subsidy - the split never discounts the customer fee, see ShippingFeeCalculator STEP 12)
     *   'platform_subsidy'       => int,  // actual platform pays toward the carrier-cost gap
     *   'vendor_contribution'    => int,  // vendor's share of the gap, plus the full fee if vendor_covers_delivery=1
     *   'customer_pays'          => int,  // what customer is charged (0 if free-threshold/vendor-covered)
     *   'is_free_for_customer'   => bool,
     *   'is_free_by_platform'    => bool,
     *   'is_free_by_vendor'      => bool,
     *   'billable_weight_grams'  => int,
     * ]
     *
     * @param  Collection<int, CartItem>  $vendorCartItems  all cart items belonging to one vendor
     */
    public function resolve(
        Collection $vendorCartItems,
        ShippingZone $zone,
        ShippingMethod $method
    ): array {
        if ($vendorCartItems->isEmpty()) {
            return $this->noShippingAvailable();
        }

        // Free-shipping-threshold input Calculator needs (STEP 8). resolve()
        // only sees this one vendor's items, so this is the vendor's own
        // subtotal - the same subtotal CheckoutController computes per group
        // in resolveVendorShipping().
        $orderSubtotal = (int) $vendorCartItems->sum(fn ($item) => $item->unit_price * $item->quantity);

        $billable = 0;
        $customerPays = 0;
        $carrierCost = 0;
        $adminSubsidy = 0;
        $vendorContribution = 0;
        $sawRate = false;

        foreach ($vendorCartItems as $item) {
            // Resolved via CartLineSource, not `$item->vendorListing`, so a
            // campaign-marketer cart item (whose vendor_listing_id column is
            // null) still uses its fulfilment listing (P-02).
            $listing = CartLineSource::resolve($item)?->fulfilmentListing ?? $item->vendorListing;

            $result = $this->feeCalculator->calculate([
                'listing' => $listing,
                'destination_zone_id' => $zone->id,
                'shipping_method_id' => $method->id,
                'payment_method' => 'other',
                'destination_city_id' => null,
                'country_id' => $zone->country_id,
                'order_subtotal' => $orderSubtotal,
            ]);

            if ($result === null) {
                continue;
            }

            $sawRate = true;
            $billable += (int) ($result['billable_weight_grams'] ?? 0);
            $customerPays += $result['fee'];
            $carrierCost += $result['carrier_shipping_cost'];
            $adminSubsidy += $result['admin_subsidy_amount'];
            $vendorContribution += $result['vendor_contribution'];
        }

        if (! $sawRate) {
            return $this->noShippingAvailable($billable);
        }

        return [
            'raw_fee' => $carrierCost,
            'subsidy_cap' => $adminSubsidy,
            'platform_subsidy' => $adminSubsidy,
            'vendor_contribution' => $vendorContribution,
            'customer_pays' => $customerPays,
            'is_free_for_customer' => $customerPays === 0,
            'is_free_by_platform' => $customerPays === 0 && $vendorContribution === 0 && $carrierCost > 0,
            'is_free_by_vendor' => $customerPays === 0 && $vendorContribution > 0,
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
