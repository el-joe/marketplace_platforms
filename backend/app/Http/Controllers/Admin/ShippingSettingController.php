<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CountryShippingSetting;
use App\Models\ShippingCarrier;
use App\Models\ShippingCompany;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingCarrierFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ShippingSettingController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $methods = ShippingMethod::orderBy('name')->get();
        $carriers = ShippingCarrier::orderBy('name')->get();
        $shippingCompanies = ShippingCompany::active()->orderBy('name')->get(['id', 'name']);
        $countries = Country::where('is_active', 1)
            ->with('countryShippingSettings')
            ->orderBy('name_en')
            ->get();
        $zones = ShippingZone::with('country')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('admin.shipping-settings.index', compact('methods', 'carriers', 'shippingCompanies', 'countries', 'zones'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Carriers CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function storeCarrier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:shipping_carriers,code'],
            'shipping_company_id' => ['nullable', 'string', 'exists:shipping_companies,id'],
            'country_id' => ['nullable', 'string', 'exists:countries,id'],
            'api_endpoint' => ['nullable', 'url', 'max:255'],
            'tracking_url_pattern' => ['nullable', 'string', 'max:500'],
            'supports_cod' => ['boolean'],
            'supports_returns' => ['boolean'],
            'is_active' => ['boolean'],
            'credentials' => ['nullable', 'string'],
        ]);

        $credentials = null;
        if (!empty($data['credentials'])) {
            $decoded = json_decode($data['credentials'], true);
            if (!is_array($decoded)) {
                return response()->json(['message' => 'Credentials must be valid JSON.'], 422);
            }
            $credentials = Crypt::encryptString($data['credentials']);
        }

        unset($data['credentials']);
        $data['credentials_encrypted'] = $credentials;

        $carrier = ShippingCarrier::create($data);

        return response()->json(['success' => true, 'message' => 'Carrier created.', 'data' => $carrier], 201);
    }

    public function updateCarrier(Request $request, ShippingCarrier $carrier): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'shipping_company_id' => ['nullable', 'string', 'exists:shipping_companies,id'],
            'country_id' => ['nullable', 'string', 'exists:countries,id'],
            'api_endpoint' => ['nullable', 'url', 'max:255'],
            'tracking_url_pattern' => ['nullable', 'string', 'max:500'],
            'supports_cod' => ['sometimes', 'boolean'],
            'supports_returns' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'credentials' => ['nullable', 'string'],
        ]);

        if (!empty($data['credentials'])) {
            $decoded = json_decode($data['credentials'], true);
            if (!is_array($decoded)) {
                return response()->json(['message' => 'Credentials must be valid JSON.'], 422);
            }
            $data['credentials_encrypted'] = Crypt::encryptString($data['credentials']);
        }

        unset($data['credentials']);
        $carrier->update($data);

        return response()->json(['success' => true, 'message' => 'Carrier updated.', 'data' => $carrier->fresh()]);
    }

    public function toggleCarrier(ShippingCarrier $carrier): JsonResponse
    {
        $carrier->update(['is_active' => !$carrier->is_active]);

        return response()->json(['success' => true, 'is_active' => $carrier->is_active]);
    }

    public function testCarrier(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $carrier = app(ShippingCarrierFactory::class)->make($request->code);
        $result = $carrier->testConnection();

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shipping Rates DataTable + CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function ratesDatatable(Request $request): JsonResponse
    {
        $query = ShippingRate::query()
            ->with(['destinationZone.country', 'shippingMethod', 'carrier'])
            ->select('shipping_rates.*');

        if ($request->filled('shipping_method_id')) {
            $query->where('shipping_method_id', $request->shipping_method_id);
        }

        if ($request->filled('carrier_id')) {
            $query->where('carrier_id', $request->carrier_id);
        }

        if ($request->filled('zone_id')) {
            $query->where('destination_zone_id', $request->zone_id);
        }

        // DataTables server-side
        $draw = (int) $request->draw;
        $start = (int) $request->start;
        $length = (int) ($request->length ?? 25);

        $total = $query->count();
        $filtered = $total;

        $rates = $query->skip($start)->take($length)->get();

        $data = $rates->map(function (ShippingRate $rate) {
            return [
                'id' => $rate->id,
                'zone_name' => optional($rate->destinationZone)->name ?? '—',
                'method_name' => optional($rate->shippingMethod)->name ?? '—',
                'carrier_name' => optional($rate->carrier)->name ?? 'Any',
                'base_fee_formatted' => number_format($rate->base_fee, 2),
                'rate_per_kg_formatted' => number_format($rate->rate_per_kg, 2),
                'free_threshold_formatted' => $rate->free_shipping_threshold
                    ? number_format($rate->free_shipping_threshold, 2)
                    : '—',
                'cod_fee_formatted' => number_format($rate->cod_extra_fee, 2),
                'is_active' => $rate->is_active,
                'active_badge' => $rate->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-gray">Inactive</span>',
                'shipping_method_id' => $rate->shipping_method_id,
                'destination_zone_id' => $rate->destination_zone_id,
                'carrier_id' => $rate->carrier_id,
                'base_fee' => $rate->base_fee,
                'rate_per_kg' => $rate->rate_per_kg,
                'min_weight_grams' => $rate->min_weight_grams,
                'volumetric_divisor' => $rate->volumetric_divisor,
                'free_shipping_threshold' => $rate->free_shipping_threshold,
                'cod_extra_fee' => $rate->cod_extra_fee,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'destination_zone_id' => ['required', 'exists:shipping_zones,id'],
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'carrier_id' => ['nullable', 'exists:shipping_carriers,id'],
            'origin_zone_id' => ['nullable', 'exists:shipping_zones,id'],
            'base_fee' => ['required', 'integer', 'min:0'],
            'rate_per_kg' => ['required', 'integer', 'min:0'],
            'min_weight_grams' => ['nullable', 'integer', 'min:0'],
            'volumetric_divisor' => ['nullable', 'integer', 'min:1'],
            'free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
            'cod_extra_fee' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $rate = ShippingRate::create($data);

        return response()->json(['success' => true, 'message' => 'Shipping rate created.', 'data' => $rate], 201);
    }

    public function updateRate(Request $request, ShippingRate $rate): JsonResponse
    {
        $data = $request->validate([
            'destination_zone_id' => ['sometimes', 'exists:shipping_zones,id'],
            'shipping_method_id' => ['sometimes', 'exists:shipping_methods,id'],
            'carrier_id' => ['nullable', 'exists:shipping_carriers,id'],
            'origin_zone_id' => ['nullable', 'exists:shipping_zones,id'],
            'base_fee' => ['sometimes', 'integer', 'min:0'],
            'rate_per_kg' => ['sometimes', 'integer', 'min:0'],
            'min_weight_grams' => ['nullable', 'integer', 'min:0'],
            'volumetric_divisor' => ['nullable', 'integer', 'min:1'],
            'free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
            'cod_extra_fee' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rate->update($data);

        return response()->json(['success' => true, 'message' => 'Shipping rate updated.', 'data' => $rate->fresh()]);
    }

    public function destroyRate(ShippingRate $rate): JsonResponse
    {
        $rate->delete();

        return response()->json(['success' => true, 'message' => 'Shipping rate deleted.']);
    }

    public function toggleRate(ShippingRate $rate): JsonResponse
    {
        $rate->update(['is_active' => !$rate->is_active]);

        return response()->json(['success' => true, 'is_active' => $rate->is_active]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Country Settings
    // ─────────────────────────────────────────────────────────────────────────

    public function countrySettings(Request $request): JsonResponse
    {
        $settings = CountryShippingSetting::with(['country', 'shippingMethod'])
            ->get()
            ->groupBy('country_id');

        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function upsertCountrySetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'is_active' => ['sometimes', 'boolean'],
            'free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $setting = CountryShippingSetting::updateOrCreate(
            [
                'country_id' => $data['country_id'],
                'shipping_method_id' => $data['shipping_method_id'],
            ],
            array_filter([
                'is_active' => $data['is_active'] ?? null,
                'free_shipping_threshold' => $data['free_shipping_threshold'] ?? null,
            ], fn($v) => $v !== null)
        );

        return response()->json(['success' => true, 'message' => 'Setting saved.', 'data' => $setting]);
    }
}
