<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminListing;
use App\Models\InternationalShippingEligibility;
use App\Models\VendorListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * docs/plans/international_product_shipping.md Phase 5.
 *
 * "Ships to" destination picker for the vendor-listing and admin-listing edit
 * views. A row in international_shipping_eligibility = "this listing can ship
 * to this destination country" (design decision #2 — uniform opt-in for both
 * listing types, following marketplace_shipping_rules's vendor_listing_id /
 * admin_listing_id nullable-pair convention: exactly one of the two is set,
 * enforced here at the application layer since the migration has no DB-level
 * CHECK constraint, matching marketplace_shipping_rules).
 *
 * Submitting the picker syncs eligibility for the listing: countries checked
 * are set/created as is_active = true; countries left unchecked that already
 * have a row are set to is_active = false (never deleted — keeps history,
 * consistent with is_active being the toggle everywhere else in this table).
 */
class InternationalShippingEligibilityController extends Controller
{
    public function updateForVendorListing(Request $request, VendorListing $vendorListing): RedirectResponse
    {
        $data = $request->validate([
            'destination_country_ids' => ['array'],
            'destination_country_ids.*' => ['string', 'exists:countries,id'],
        ]);

        $this->sync(
            selectedCountryIds: $data['destination_country_ids'] ?? [],
            listingColumn: 'vendor_listing_id',
            listingId: $vendorListing->id,
            ownCountryId: $vendorListing->country_id,
        );

        return redirect()
            ->route('admin.vendor-listings.edit', $vendorListing)
            ->with('success', 'Ships-to destinations updated.');
    }

    public function updateForAdminListing(Request $request, AdminListing $adminListing): RedirectResponse
    {
        $data = $request->validate([
            'destination_country_ids' => ['array'],
            'destination_country_ids.*' => ['string', 'exists:countries,id'],
        ]);

        $this->sync(
            selectedCountryIds: $data['destination_country_ids'] ?? [],
            listingColumn: 'admin_listing_id',
            listingId: $adminListing->id,
            ownCountryId: $adminListing->country_id,
        );

        return redirect()
            ->route('admin.admin-listings.edit', $adminListing)
            ->with('success', 'Ships-to destinations updated.');
    }

    /**
     * @param string[] $selectedCountryIds
     */
    private function sync(array $selectedCountryIds, string $listingColumn, string $listingId, ?string $ownCountryId): void
    {
        // A listing can never "ship to" its own origin country — that's domestic, not international.
        $selectedCountryIds = array_values(array_filter(
            array_unique($selectedCountryIds),
            fn (string $countryId) => $countryId !== $ownCountryId
        ));

        $existing = InternationalShippingEligibility::query()
            ->where($listingColumn, $listingId)
            ->get()
            ->keyBy('destination_country_id');

        foreach ($selectedCountryIds as $destinationCountryId) {
            if ($existing->has($destinationCountryId)) {
                $existing->get($destinationCountryId)->update(['is_active' => true]);
            } else {
                InternationalShippingEligibility::create([
                    $listingColumn => $listingId,
                    'destination_country_id' => $destinationCountryId,
                    'is_active' => true,
                ]);
            }
        }

        foreach ($existing as $destinationCountryId => $row) {
            if (!in_array($destinationCountryId, $selectedCountryIds, true) && $row->is_active) {
                $row->update(['is_active' => false]);
            }
        }
    }
}
