<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\InternationalShippingRate;
use App\Models\ShippingCarrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * docs/plans/international_product_shipping.md Phase 5.
 *
 * Plain CRUD for international_shipping_rates (origin country x destination
 * country x carrier -> fees/ETA), mirroring ShippingMethodController's
 * resource-controller shape (index/create/store/edit/update/destroy, server
 * rendered Blade forms, no AJAX datatable).
 */
class InternationalShippingRateController extends Controller
{
    public function index(): View
    {
        $rates = InternationalShippingRate::query()
            ->with(['originCountry', 'destinationCountry', 'carrier'])
            ->orderBy('origin_country_id')
            ->orderBy('destination_country_id')
            ->get();

        return view('admin.international-shipping-rates.index', [
            'rates' => $rates,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'International Shipping Rates'],
            ],
        ]);
    }

    public function create(): View
    {
        $rate = new InternationalShippingRate([
            'is_active' => true,
        ]);

        return view('admin.international-shipping-rates.create', [
            'rate' => $rate,
            'countries' => $this->countries(),
            'carriers' => $this->carriers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        InternationalShippingRate::create($data);

        return redirect()->route('admin.international-shipping-rates.index')
            ->with('success', 'International shipping rate created.');
    }

    public function edit(InternationalShippingRate $internationalShippingRate): View
    {
        return view('admin.international-shipping-rates.edit', [
            'rate' => $internationalShippingRate,
            'countries' => $this->countries(),
            'carriers' => $this->carriers(),
        ]);
    }

    public function update(Request $request, InternationalShippingRate $internationalShippingRate): RedirectResponse
    {
        $data = $this->validateData($request, $internationalShippingRate);

        $internationalShippingRate->update($data);

        return redirect()->route('admin.international-shipping-rates.index')
            ->with('success', 'International shipping rate updated.');
    }

    public function destroy(InternationalShippingRate $internationalShippingRate): RedirectResponse
    {
        $internationalShippingRate->delete();

        return redirect()->route('admin.international-shipping-rates.index')
            ->with('success', 'International shipping rate deleted.');
    }

    private function validateData(Request $request, ?InternationalShippingRate $rate = null): array
    {
        $data = $request->validate([
            'origin_country_id' => ['required', 'exists:countries,id'],
            'destination_country_id' => ['required', 'exists:countries,id', 'different:origin_country_id'],
            'carrier_id' => ['nullable', 'exists:shipping_carriers,id'],
            'base_fee' => ['required', 'integer', 'min:0'],
            'rate_per_kg' => ['required', 'integer', 'min:0'],
            'customs_fee_flat' => ['nullable', 'integer', 'min:0'],
            'min_eta_days' => ['required', 'integer', 'min:0'],
            'max_eta_days' => ['required', 'integer', 'min:0', 'gte:min_eta_days'],
            'is_active' => ['boolean'],
        ]);

        // Every money column is BIGINT storing base-currency integer minor units — never a float.
        $data['base_fee'] = (int) $data['base_fee'];
        $data['rate_per_kg'] = (int) $data['rate_per_kg'];
        $data['customs_fee_flat'] = isset($data['customs_fee_flat']) ? (int) $data['customs_fee_flat'] : null;

        return $data;
    }

    private function countries()
    {
        return Country::orderBy('name_en')->get();
    }

    private function carriers()
    {
        return ShippingCarrier::where('is_active', true)->orderBy('name')->get();
    }
}
