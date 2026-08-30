<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShippingRateRequest;
use App\Http\Requests\Admin\StoreShippingZoneRequest;
use App\Http\Requests\Admin\UpdateShippingRateRequest;
use App\Http\Requests\Admin\UpdateShippingZoneRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\ShippingCarrier;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingRateService;
use App\Services\Shipping\ShippingZoneService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShippingZoneController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private readonly ShippingZoneService $zoneService,
        private readonly ShippingRateService $rateService
    ) {
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Pages
    // ═══════════════════════════════════════════════════════════════════════

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportZones($request);
        }

        $countries = Country::where('is_active', 1)->orderBy('name_en')->get();

        $activeCountryId = request('country', $countries->first()?->id);

        $zones = $this->buildZonesQuery($request)
            ->forCountry($activeCountryId)
            ->orderBy('name')
            ->get();

        // Append active_rate_count manually (uses accessor that queries DB)
        $zones->each(fn($z) => $z->append(['active_rate_count']));

        $methods = ShippingMethod::where('is_active', 1)->get();
        $carriers = ShippingCarrier::where('is_active', 1)->get();

        $unassignedCities = City::unassigned()
            ->forCountry($activeCountryId)
            ->orderBy('name_en')
            ->get();

        return view('admin.shipping-zones.index', compact(
            'countries',
            'zones',
            'methods',
            'carriers',
            'unassignedCities',
            'activeCountryId'
        ));
    }

    public function show(ShippingZone $zone): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'zone' => $zone->load('country'),
                'active_rate_count' => $zone->active_rate_count,
                'city_count' => $zone->city_count,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Zone CRUD
    // ═══════════════════════════════════════════════════════════════════════

    public function store(StoreShippingZoneRequest $request): JsonResponse
    {
        $zone = $this->zoneService->createZone(
            $request->validated(),
            auth()->guard('admin')->user()
        );

        return response()->json([
            'success' => true,
            'data' => ['zone' => $zone->load('country')],
            'message' => 'Zone created successfully.',
        ]);
    }

    public function update(UpdateShippingZoneRequest $request, ShippingZone $zone): JsonResponse
    {
        $zone = $this->zoneService->updateZone(
            $zone,
            $request->validated(),
            auth()->guard('admin')->user()
        );

        return response()->json([
            'success' => true,
            'data' => ['zone' => $zone],
            'message' => 'Zone updated successfully.',
        ]);
    }

    public function destroy(ShippingZone $zone): JsonResponse
    {
        try {
            $this->zoneService->deleteZone($zone);

            return response()->json([
                'success' => true,
                'message' => 'Zone deleted.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function toggleActive(ShippingZone $zone): JsonResponse
    {
        $zone->update(['is_active' => !$zone->is_active]);

        return response()->json([
            'success' => true,
            'data' => ['is_active' => $zone->is_active],
        ]);
    }

    public function duplicate(Request $request, ShippingZone $zone): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        $new = $this->zoneService->duplicateZone(
            $zone,
            $request->name,
            auth()->guard('admin')->user()
        );

        return response()->json([
            'success' => true,
            'data' => ['zone' => $new],
            'message' => 'Zone duplicated with ' . $new->destinationRates()->count() . ' rates.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // City assignment
    // ═══════════════════════════════════════════════════════════════════════

    public function getCities(ShippingZone $zone): JsonResponse
    {
        $zone = $this->zoneService->getZoneWithCities($zone->id);

        return response()->json([
            'success' => true,
            'data' => [
                'cities' => $zone->cities->map(fn($c) => [
                    'id' => $c->id,
                    'name_en' => $c->name_en,
                    'name_ar' => $c->name_ar,
                    'cod_available' => $c->cod_available,
                    'is_active' => $c->is_active,
                ]),
            ],
        ]);
    }

    public function assignCities(Request $request, ShippingZone $zone): JsonResponse
    {
        $request->validate([
            'city_ids' => 'required|array',
            'city_ids.*' => 'exists:cities,id',
        ]);

        $result = $this->zoneService->assignCities($zone, $request->city_ids);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['assigned'] . ' cities assigned to zone.',
        ]);
    }

    public function unassignCity(Request $request): JsonResponse
    {
        $request->validate(['city_id' => 'required|exists:cities,id']);
        $city = City::findOrFail($request->city_id);
        $this->zoneService->unassignCity($city);

        return response()->json([
            'success' => true,
            'message' => $city->name_en . ' removed from zone.',
        ]);
    }

    public function getUnassigned(Request $request): JsonResponse
    {
        $countryId = $request->country_id;

        $cities = City::unassigned()
            ->when($countryId, fn($q) => $q->forCountry($countryId))
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'country_id', 'cod_available']);

        return response()->json([
            'success' => true,
            'data' => ['cities' => $cities],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Rates
    // ═══════════════════════════════════════════════════════════════════════

    public function getRates(Request $request): JsonResponse
    {
        $query = ShippingRate::query()
            ->with(['destinationZone', 'shippingMethod', 'carrier'])
            ->when($request->destination_zone_id, fn($q, $v) => $q->where('destination_zone_id', $v))
            ->when($request->shipping_method_id, fn($q, $v) => $q->where('shipping_method_id', $v))
            ->when($request->carrier_id, fn($q, $v) => $q->where('carrier_id', $v))
            ->when($request->has('is_active') && !in_array($request->is_active, ['', null]), fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->orderByDesc('created_at');

        if ($request->country_id) {
            $query->whereHas('destinationZone', fn($q) => $q->where('country_id', $request->country_id));
        }

        $rates = $query->get();
        // dd($query->toRawSql(), $request->is_active);
        return response()->json([
            'success' => true,
            'data' => $rates->map(fn($r) => $this->formatRate($r)),
        ]);
    }

    public function storeRate(StoreShippingRateRequest $request): JsonResponse
    {
        $rate = $this->rateService->createRate(
            $request->validated(),
            auth()->guard('admin')->user()
        );

        return response()->json([
            'success' => true,
            'data' => ['rate' => $this->formatRate($rate)],
            'message' => 'Rate created.',
        ]);
    }

    public function updateRate(UpdateShippingRateRequest $request, ShippingRate $rate): JsonResponse
    {
        $updated = $this->rateService->updateRate(
            $rate,
            $request->validated(),
            auth()->guard('admin')->user()
        );

        return response()->json([
            'success' => true,
            'data' => ['rate' => $this->formatRate($updated)],
            'message' => 'Rate updated.',
        ]);
    }

    public function destroyRate(ShippingRate $rate): JsonResponse
    {
        $this->rateService->deleteRate($rate);

        return response()->json([
            'success' => true,
            'message' => 'Rate deleted.',
        ]);
    }

    public function toggleRate(ShippingRate $rate): JsonResponse
    {
        $rate->update(['is_active' => !$rate->is_active]);

        return response()->json([
            'success' => true,
            'data' => ['is_active' => $rate->is_active],
        ]);
    }

    public function bulkRates(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:shipping_rates,id',
        ]);

        $count = match ($request->action) {
            'activate' => $this->rateService->bulkToggle($request->ids, true),
            'deactivate' => $this->rateService->bulkToggle($request->ids, false),
            'delete' => $this->rateService->bulkDelete($request->ids),
        };

        return response()->json([
            'success' => true,
            'message' => $count . ' rates ' . $request->action . 'd.',
        ]);
    }

    public function copyRates(Request $request): JsonResponse
    {
        $request->validate([
            'source_zone_id' => 'required|exists:shipping_zones,id',
            'target_zone_id' => 'required|exists:shipping_zones,id|different:source_zone_id',
        ]);

        $count = $this->rateService->duplicateRatesForZone(
            ShippingZone::findOrFail($request->source_zone_id),
            ShippingZone::findOrFail($request->target_zone_id)
        );

        return response()->json([
            'success' => true,
            'message' => $count . ' rates copied.',
        ]);
    }

    public function calculateEstimate(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => 'required|exists:shipping_zones,id',
            'method_id' => 'required|exists:shipping_methods,id',
            'weight_grams' => 'required|integer|min:1',
            'order_value' => 'required|numeric|min:0',
            'is_cod' => 'boolean',
        ]);

        $result = $this->rateService->calculateEstimate(
            $request->zone_id,
            $request->method_id,
            (int) $request->weight_grams,
            (int) round((float) $request->order_value),
            $request->boolean('is_cod')
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function buildZonesQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = ShippingZone::withCount(['cities'])->with('country');

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where('shipping_zones.name', 'like', '%' . $v . '%'),
            'country_id' => fn($q, $v) => $q->where('shipping_zones.country_id', $v),
        ]);
    }

    private function exportZones(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $zones = $this->buildZonesQuery($request)->orderBy('name')->get();
        $zones->each(fn($z) => $z->append(['active_rate_count']));

        $headers = ['Zone Name', 'Country', 'Cities Count', 'Active', 'Created'];

        $rows = $zones->map(fn($zone) => [
            $zone->name,
            $zone->country?->name_en,
            (int) $zone->cities_count,
            $zone->is_active ? 'Yes' : 'No',
            optional($zone->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('shipping_zones', $headers, $rows),
            'csv' => $this->exportCsv('shipping_zones', $headers, $rows),
            'word' => $this->exportWord('shipping_zones', 'Shipping Zones', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function formatRate(ShippingRate $rate): array
    {
        $rate->load(['destinationZone', 'shippingMethod', 'carrier']);

        return [
            'id' => $rate->id,
            'destination_zone' => $rate->destinationZone->name,
            'destination_zone_id' => $rate->destination_zone_id,
            'origin_zone' => $rate->originZone?->name ?? 'Any',
            'origin_zone_id' => $rate->origin_zone_id,
            'method_name' => $rate->shippingMethod->name,
            'shipping_method_id' => $rate->shipping_method_id,
            'carrier_name' => $rate->carrier?->name ?? 'Any',
            'carrier_id' => $rate->carrier_id,
            'base_fee' => $rate->base_fee,
            'base_fee_formatted' => $rate->base_fee_formatted,
            'carrier_rate' => $rate->carrier_rate,
            'carrier_rate_per_kg' => $rate->carrier_rate_per_kg,
            'rate_per_kg' => $rate->rate_per_kg,
            'rate_per_kg_formatted' => $rate->rate_per_kg_formatted,
            'free_threshold' => $rate->free_shipping_threshold,
            'free_threshold_formatted' => $rate->free_threshold_formatted,
            'cod_extra_fee' => $rate->cod_extra_fee,
            'cod_extra_fee_formatted' => $rate->cod_extra_fee_formatted,
            'min_weight_grams' => $rate->min_weight_grams,
            'volumetric_divisor' => $rate->volumetric_divisor,
            'is_active' => $rate->is_active,
        ];
    }
}
