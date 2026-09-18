<?php

namespace App\Services\Shipping;

use App\Models\AdminListing;
use App\Models\Country;
use App\Models\InternationalShippingEligibility;
use App\Models\InternationalShippingRate;
use App\Models\VendorListing;

/**
 * docs/plans/international_product_shipping.md Phase 6.
 *
 * Read-only lookup for the customer-facing "Ships from {country}" indicator:
 * given a listing and the customer's currently-selected storefront (destination)
 * country, resolves whether that listing's own country differs, is actually
 * eligible to ship there (an active international_shipping_eligibility row),
 * and — if so — the ETA range from the matching international_shipping_rates
 * corridor row. Mirrors CartLineSource::isInternational()/originCountryId()
 * and InternationalShippingRateService's rate-lookup precedence
 * (carrier-specific row preferred over the generic NULL-carrier fallback),
 * without touching checkout/pricing at all — this never computes a fee.
 *
 * Returns null whenever the listing isn't international for this
 * destination, isn't eligible, or has no active rate corridor — callers
 * render nothing extra in that case (domestic listings are always null).
 */
class ListingOriginShippingIndicator
{
    /**
     * @return array{
     *     origin_country: array{id: string, name: array{ar: ?string, en: ?string}},
     *     min_eta_days: int,
     *     max_eta_days: int,
     *     customs_included: true,
     * }|null
     */
    public function resolve(VendorListing|AdminListing $listing, Country $destinationCountry): ?array
    {
        $originCountryId = $listing->country_id;

        if (! $originCountryId || $originCountryId === $destinationCountry->id) {
            return null;
        }

        $eligibilityQuery = InternationalShippingEligibility::query()
            ->where('destination_country_id', $destinationCountry->id)
            ->where('is_active', true);

        if ($listing instanceof VendorListing) {
            $eligibilityQuery->where('vendor_listing_id', $listing->id);
        } else {
            $eligibilityQuery->where('admin_listing_id', $listing->id);
        }

        if (! $eligibilityQuery->exists()) {
            return null;
        }

        $rate = InternationalShippingRate::query()
            ->where('origin_country_id', $originCountryId)
            ->where('destination_country_id', $destinationCountry->id)
            ->where('is_active', true)
            ->orderByRaw('carrier_id IS NULL')
            ->first();

        if (! $rate) {
            return null;
        }

        $originCountry = $originCountryId === $destinationCountry->id
            ? $destinationCountry
            : Country::find($originCountryId);

        if (! $originCountry) {
            return null;
        }

        return [
            'origin_country' => [
                'id' => $originCountry->id,
                'name' => [
                    'ar' => $originCountry->name_ar,
                    'en' => $originCountry->name_en,
                ],
            ],
            'min_eta_days' => $rate->min_eta_days,
            'max_eta_days' => $rate->max_eta_days,
            // Per the Phase 3 DDP decision: international quotes always bundle
            // customs into the fee shown at checkout, so this is always true
            // whenever we return a non-null indicator at all.
            'customs_included' => true,
        ];
    }
}
