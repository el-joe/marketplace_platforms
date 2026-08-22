<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\PlatformShippingSubsidy;
use App\Models\ShippingCarrier;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\VendorAdmin;
use App\Models\VendorExceptionalZoneAlert;
use App\Models\VendorExceptionalZoneAlertResult;
use App\Models\Warehouse;
use App\Models\WarehouseExceptionalZone;
use App\Notifications\Vendor\VendorExceptionalZoneAccepted;
use App\Notifications\Vendor\VendorExceptionalZoneRejected;
use App\Traits\HasDataTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShippingSubsidyController extends Controller
{
    use HasDataTable;

    public function index(Request $request): View|JsonResponse
    {
        if (!$request->has('draw')) {
            $countries = Country::where('is_active', 1)->orderBy('name_en')->get();
            $zones = ShippingZone::with('country')->orderBy('name')->get();
            $exceptionalZones = $zones->filter(fn(ShippingZone $zone) => $zone->hasGapRates())->values();
            $methods = ShippingMethod::where('is_active', 1)->orderBy('name')->get();
            $currencies = Currency::where('is_active', 1)->orderBy('code')->get();
            $warehouses = Warehouse::orderBy('name')->get();

            return view('admin.shipping_subsidies.index', compact('countries', 'zones', 'exceptionalZones', 'methods', 'currencies', 'warehouses'));
        }

        $query = PlatformShippingSubsidy::query()
            ->with(['warehouse', 'shippingZone.country', 'shippingMethod']);

        if ($request->filled('country_id')) {
            $query->whereHas('shippingZone', fn($q) => $q->where('country_id', $request->country_id));
        }

        $columns = [
            [],
            [],
            [],
            [],
            ['orderable_column' => 'subsidy_cap'],
            ['orderable_column' => 'max_subsidy_weight_grams'],
            [],
            [],
            [],
            [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (PlatformShippingSubsidy $subsidy) {
            return $this->formatSubsidy($subsidy);
        });
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateSubsidy($request);

        $this->assertNoDuplicate($data);

        $subsidy = PlatformShippingSubsidy::updateOrCreate(
            [
                'warehouse_id' => $data['warehouse_id'],
                'shipping_zone_id' => $data['shipping_zone_id'],
                'shipping_method_id' => $data['shipping_method_id'],
            ],
            $data + [
                'created_by_admin_id' => auth()->guard('admin')->id(),
            ]
        );

        $this->forgetCache($subsidy->warehouse_id, $subsidy->shipping_zone_id, $subsidy->shipping_method_id);

        return response()->json([
            'success' => true,
            'message' => 'Subsidy created.',
            'data' => $this->formatSubsidy($subsidy->fresh(['warehouse', 'shippingZone.country', 'shippingMethod'])),
        ], 201);
    }

    public function update(Request $request, PlatformShippingSubsidy $subsidy): JsonResponse
    {
        $data = $this->validateSubsidy($request);

        $this->assertNoDuplicate($data, $subsidy->id);

        $subsidy->update($data);

        $this->forgetCache($subsidy->warehouse_id, $subsidy->shipping_zone_id, $subsidy->shipping_method_id);

        return response()->json([
            'success' => true,
            'message' => 'Subsidy updated.',
            'data' => $this->formatSubsidy($subsidy->fresh(['warehouse', 'shippingZone.country', 'shippingMethod'])),
        ]);
    }

    public function destroy(PlatformShippingSubsidy $subsidy): JsonResponse
    {
        $subsidy->update(['is_active' => false]);

        $this->forgetCache($subsidy->warehouse_id, $subsidy->shipping_zone_id, $subsidy->shipping_method_id);

        return response()->json([
            'success' => true,
            'message' => 'Subsidy deactivated.',
        ]);
    }

    // ─── Vendor Exceptional Zone Alerts ────────────────────────────────────────

    public function vendorAlerts(): View
    {
        $pending = VendorExceptionalZoneAlert::where('status', 'pending')
            ->with('vendor', 'warehouse', 'carrier')
            ->latest()
            ->get()
            ->map(fn (VendorExceptionalZoneAlert $alert) => $this->enrichAlertWithZoneData($alert));

        $reviewed = VendorExceptionalZoneAlert::whereIn('status', ['accepted', 'rejected'])
            ->with('vendor', 'warehouse', 'carrier', 'reviewedBy', 'results.zone', 'results.method')
            ->latest()
            ->take(50)
            ->get();

        $methods = ShippingMethod::where('is_active', 1)->orderBy('name')->get();
        $carriers = ShippingCarrier::where('is_active', 1)->orderBy('name')->get();

        return view('admin.shipping_subsidies.vendor-alerts', compact('pending', 'reviewed', 'methods', 'carriers'));
    }

    /**
     * Groups an alert's reported cities by their assigned shipping zone
     * (flagging cities with no zone) so the admin can pick which zones to
     * configure a subsidy for.
     */
    private function enrichAlertWithZoneData(VendorExceptionalZoneAlert $alert): array
    {
        $cities = City::whereIn('id', $alert->city_ids ?? [])
            ->with('shippingZone:id,name')
            ->get();

        $zonesDetected = $cities
            ->groupBy(fn (City $city) => $city->shipping_zone_id ?? '__none__')
            ->map(function ($citiesInZone, $zoneId) {
                return [
                    'zone_id' => $zoneId === '__none__' ? null : $zoneId,
                    'zone_name' => $zoneId === '__none__'
                        ? 'No Zone Assigned'
                        : ($citiesInZone->first()->shippingZone->name ?? 'Unknown Zone'),
                    'cities' => $citiesInZone,
                    'has_zone' => $zoneId !== '__none__',
                ];
            })
            ->values();

        $allZonesInCountry = ShippingZone::where('country_id', $alert->warehouse->country_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'alert' => $alert,
            'zones_detected' => $zonesDetected,
            'all_zones_country' => $allZonesInCountry,
            'has_unzoned_cities' => $zonesDetected->where('has_zone', false)->isNotEmpty(),
        ];
    }

    /**
     * Admin selects one or more zones (detected from the reported cities, or
     * any other zone in the warehouse's country) and configures a subsidy +
     * carrier rate for each.
     */
    public function acceptAlert(Request $request, VendorExceptionalZoneAlert $alert): RedirectResponse
    {
        abort_unless($alert->isPending(), 422, 'Alert is no longer pending.');

        $request->validate([
            'zone_configs' => 'required|array|min:1',
            'zone_configs.*.zone_id' => 'required|exists:shipping_zones,id',
            'zone_configs.*.shipping_method_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value !== 'all' && !ShippingMethod::where('id', $value)->exists()) {
                        $fail('The selected shipping method is invalid.');
                    }
                },
            ],
            'zone_configs.*.carrier_rate' => 'required|integer|min:1',
            'zone_configs.*.carrier_rate_per_kg' => 'required|integer|min:0',
            'zone_configs.*.split_type' => 'required|in:percentage,fixed',
            'zone_configs.*.vendor_share_pct' => 'required_if:zone_configs.*.split_type,percentage|integer|min:0|max:100',
            'zone_configs.*.vendor_fixed_amount' => 'required_if:zone_configs.*.split_type,fixed|integer|min:0',
            'zone_configs.*.admin_fixed_amount' => 'required_if:zone_configs.*.split_type,fixed|integer|min:0',
            'zone_configs.*.subsidy_cap' => 'required|integer|min:0',
            'zone_configs.*.currency' => 'required|in:SAR,AED,OMR,KWD,QAR,BHD,EGP,JOD',
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $alert) {
            $warehouse = $alert->warehouse;
            $originZoneId = $warehouse->shipping_zone_id;
            $adminId = auth()->guard('admin')->id();

            $results = [];
            $allMethodIds = ShippingMethod::where('is_active', 1)->pluck('id');

            foreach ($request->zone_configs as $config) {
                $zoneId = $config['zone_id'];

                $methodIds = $config['shipping_method_id'] === 'all'
                    ? $allMethodIds
                    : collect([$config['shipping_method_id']]);

                $exceptionalZone = WarehouseExceptionalZone::updateOrCreate(
                    [
                        'warehouse_id' => $alert->warehouse_id,
                        'destination_zone_id' => $zoneId,
                        'carrier_id' => $alert->carrier_id,
                    ],
                    [
                        'is_active' => true,
                        'source_alert_id' => $alert->id,
                    ]
                );

                foreach ($methodIds as $methodId) {
                    ShippingRate::updateOrCreate(
                        [
                            'origin_zone_id' => $originZoneId,
                            'destination_zone_id' => $zoneId,
                            'shipping_method_id' => $methodId,
                            'carrier_id' => $alert->carrier_id,
                        ],
                        [
                            'carrier_rate' => $config['carrier_rate'],
                            'carrier_rate_per_kg' => $config['carrier_rate_per_kg'],
                        ]
                    );

                    $subsidy = PlatformShippingSubsidy::updateOrCreate(
                        [
                            'warehouse_id' => $alert->warehouse_id,
                            'shipping_zone_id' => $zoneId,
                            'shipping_method_id' => $methodId,
                            'carrier_id' => $alert->carrier_id,
                        ],
                        [
                            'currency' => $config['currency'],
                            'split_type' => $config['split_type'],
                            'vendor_share_pct' => $config['vendor_share_pct'] ?? 50,
                            'vendor_fixed_amount' => $config['vendor_fixed_amount'] ?? 0,
                            'admin_fixed_amount' => $config['admin_fixed_amount'] ?? 0,
                            'subsidy_cap' => $config['subsidy_cap'],
                            'is_active' => true,
                            'created_by_admin_id' => $adminId,
                        ]
                    );

                    $results[] = VendorExceptionalZoneAlertResult::create([
                        'alert_id' => $alert->id,
                        'shipping_zone_id' => $zoneId,
                        'shipping_method_id' => $methodId,
                        'created_subsidy_id' => $subsidy->id,
                        'created_exceptional_zone_id' => $exceptionalZone->id,
                    ]);
                }
            }

            $alert->update([
                'status' => 'accepted',
                'admin_note' => $request->admin_note,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ]);

            $owner = VendorAdmin::where('vendor_id', $alert->vendor_id)->where('is_owner', true)->first();
            $owner?->notify(new VendorExceptionalZoneAccepted($alert, count($results)));
        });

        return back()->with('success', count($request->zone_configs) . ' zone(s) configured. Vendor has been notified.');
    }

    public function rejectAlert(Request $request, VendorExceptionalZoneAlert $alert): RedirectResponse
    {
        abort_unless($alert->isPending(), 422, 'Alert is no longer pending.');

        $request->validate(['admin_note' => 'required|string|max:500']);

        $alert->update([
            'status' => 'rejected',
            'admin_note' => $request->admin_note,
            'reviewed_by_admin_id' => auth()->guard('admin')->id(),
            'reviewed_at' => now(),
        ]);

        $owner = VendorAdmin::where('vendor_id', $alert->vendor_id)->where('is_owner', true)->first();
        $owner?->notify(new VendorExceptionalZoneRejected($alert));

        return back()->with('success', 'Alert rejected. Vendor has been notified.');
    }

    private function validateSubsidy(Request $request): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'shipping_zone_id' => ['required', 'exists:shipping_zones,id'],
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'subsidy_cap' => ['required', 'integer', 'min:0'],
            'max_subsidy_weight_grams' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'size:3', Rule::exists('currencies', 'code')],
            'split_type' => ['required', 'in:percentage,fixed'],
            'vendor_share_pct' => ['required_if:split_type,percentage', 'nullable', 'integer', 'min:0', 'max:100'],
            'vendor_fixed_amount' => ['required_if:split_type,fixed', 'nullable', 'integer', 'min:0'],
            'admin_fixed_amount' => ['required_if:split_type,fixed', 'nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $zone = ShippingZone::find($data['shipping_zone_id']);

        if (! $zone || ! $zone->hasGapRates()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shipping_zone_id' => 'Subsidies can only be configured for zones that have a recorded carrier/customer rate gap.',
            ]);
        }

        return $data;
    }

    private function assertNoDuplicate(array $data, ?string $excludeId = null): void
    {
        $query = PlatformShippingSubsidy::query()
            ->where('warehouse_id', $data['warehouse_id'])
            ->where('shipping_zone_id', $data['shipping_zone_id'])
            ->where('shipping_method_id', $data['shipping_method_id'])
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shipping_method_id' => 'An active subsidy already exists for this warehouse, zone, and method.',
            ]);
        }
    }

    private function forgetCache(?string $warehouseId, string $zoneId, string $methodId): void
    {
        Cache::forget("shipping_subsidy_{$warehouseId}_{$zoneId}_{$methodId}");
    }

    private function formatSubsidy(PlatformShippingSubsidy $subsidy): array
    {
        return [
            'id' => $subsidy->id,
            'warehouse_id' => $subsidy->warehouse_id,
            'warehouse_name' => $subsidy->warehouse?->name ?? 'All warehouses',
            'shipping_zone_id' => $subsidy->shipping_zone_id,
            'zone_name' => $subsidy->shippingZone?->name ?? '—',
            'country_name' => $subsidy->shippingZone?->country?->name_en ?? '—',
            'shipping_method_id' => $subsidy->shipping_method_id,
            'method_name' => $subsidy->shippingMethod?->name ?? '—',
            'subsidy_cap' => $subsidy->subsidy_cap,
            'max_subsidy_weight_grams' => $subsidy->max_subsidy_weight_grams,
            'currency' => $subsidy->currency,
            'split_type' => $subsidy->split_type,
            'vendor_share_pct' => $subsidy->vendor_share_pct,
            'vendor_fixed_amount' => $subsidy->vendor_fixed_amount,
            'admin_fixed_amount' => $subsidy->admin_fixed_amount,
            'is_active' => $subsidy->is_active,
            'active_badge' => $subsidy->is_active
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-gray">Inactive</span>',
        ];
    }
}
