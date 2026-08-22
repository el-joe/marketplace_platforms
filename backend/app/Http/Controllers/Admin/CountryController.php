<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\CountryCategory;
use App\Models\CountryShippingSetting;
use App\Models\Currency;
use App\Models\ShippingMethod;
use App\Services\CountryService;
use App\Traits\HasDataTable;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CountryController extends Controller
{
    use HasDataTable;

    public function __construct(private readonly CountryService $countryService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Listing
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.countries.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Countries'],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();

        $query = Country::query()
            ->withTrashed()
            ->select([
                'countries.id',
                'countries.name_en',
                'countries.name_ar',
                'countries.iso_code_2',
                'countries.currency_code',
                'countries.timezone',
                'countries.vat_rate',
                'countries.is_active',
                'countries.is_launched',
                'countries.cod_available',
                'countries.launched_at',
                'countries.deleted_at',
            ])
            ->addSelect([
                'cities_count' => City::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('country_id', 'countries.id')
                    ->whereNull('deleted_at'),
                'active_payment_gateways' => \App\Models\CountryPaymentGateway::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('country_id', 'countries.id')
                    ->where('is_active', true),
            ]);

        $query = $this->applyFilters($query, $request, [
            'is_launched' => fn($q, $v) => $q->where('countries.is_launched', (bool) $v),
            'is_active' => fn($q, $v) => $q->where('countries.is_active', (bool) $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->name_en . ' / ' . $row->name_ar,
                'iso_code_2' => $row->iso_code_2,
                'currency_code' => $row->currency_code,
                'timezone' => $row->timezone,
                'vat_rate' => $row->vat_rate . '%',
                'cities_count' => (int) $row->cities_count,
                'active_payment_gateways' => (int) $row->active_payment_gateways,
                'is_launched' => (bool) $row->is_launched,
                'is_active' => (bool) $row->is_active,
                'cod_available' => (bool) $row->cod_available,
                'is_deleted' => !is_null($row->deleted_at),
                'edit_url' => route('admin.countries.edit', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.countries.create', [
            'currencies' => Currency::where('is_active', true)->orderBy('code')->pluck('name', 'code'),
            'timezones' => $this->timezoneList(),
            'locales' => $this->localeList(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Countries', 'url' => route('admin.countries.index')],
                ['label' => 'Add Country'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'iso_code_2' => ['required', 'regex:/^[A-Z]{2}$/', 'unique:countries,iso_code_2'],
            'iso_code_3' => ['nullable', 'regex:/^[A-Z]{3}$/', 'unique:countries,iso_code_3'],
            'name_en' => ['required', 'string', 'max:100', 'unique:countries,name_en'],
            'name_ar' => ['required', 'string', 'max:100', 'unique:countries,name_ar'],
            'phone_prefix' => ['nullable', 'string', 'max:5'],
            'site_code' => ['nullable', 'regex:/^[a-z]+$/', 'max:20', 'unique:countries,site_code'],
            'site_domain' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'default_locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'timezone'],
            'vat_rate' => ['required', 'numeric', 'between:0,50'],
            'cod_available' => ['boolean'],
        ]);

        $country = Country::create(array_merge(
            ['id' => (string) Str::uuid()],
            $data,
            ['is_launched' => false, 'is_active' => true]
        ));

        return redirect()->route('admin.countries.edit', $country->id)
            ->with('success', 'Country created. Now configure payment methods and shipping.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $id): View
    {
        $country = Country::with([
            'countryShippingSettings.shippingMethod',
            'currency',
        ])->findOrFail($id);

        $allShippingMethods = ShippingMethod::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.countries.edit', [
            'country' => $country,
            'currencies' => Currency::where('is_active', true)->orderBy('code')->pluck('name', 'code'),
            'timezones' => $this->timezoneList(),
            'locales' => $this->localeList(),
            'allShippingMethods' => $allShippingMethods,
            'launchErrors' => $this->countryService->validateForLaunch($country),
            'canDelete' => $this->countryService->canDelete($country),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Countries', 'url' => route('admin.countries.index')],
                ['label' => $country->name_en],
            ],
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $country = Country::findOrFail($id);

        $oldVatRate = (float) $country->vat_rate;

        $data = $request->validate([
            'iso_code_2' => ['required', 'regex:/^[A-Z]{2}$/', "unique:countries,iso_code_2,{$id}"],
            'iso_code_3' => ['nullable', 'regex:/^[A-Z]{3}$/', "unique:countries,iso_code_3,{$id}"],
            'name_en' => ['required', 'string', 'max:100', "unique:countries,name_en,{$id}"],
            'name_ar' => ['required', 'string', 'max:100', "unique:countries,name_ar,{$id}"],
            'phone_prefix' => ['nullable', 'string', 'max:5'],
            'site_code' => ['nullable', 'regex:/^[a-z]+$/', 'max:20', "unique:countries,site_code,{$id}"],
            'site_domain' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'default_locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'timezone'],
            'vat_rate' => ['required', 'numeric', 'between:0,50'],
            'cod_available' => ['boolean'],
        ]);

        $country->update($data);

        // Log VAT rate change if it changed
        $this->countryService->logVatRateChange(
            $country,
            $oldVatRate,
            (float) $data['vat_rate'],
            auth('admin')->id()
        );

        return back()->with('success', 'Country settings saved.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Launch / Deactivate / Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function launch(string $id): JsonResponse
    {
        $country = Country::findOrFail($id);

        try {
            $this->countryService->launch($country, auth('admin')->id());
            return response()->json([
                'success' => true,
                'message' => "{$country->name_en} launched successfully. Products are being made available in the background.",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function deactivate(string $id): JsonResponse
    {
        $country = Country::findOrFail($id);
        $this->countryService->deactivate($country, auth('admin')->id());

        return response()->json(['success' => true, 'message' => "{$country->name_en} deactivated."]);
    }

    public function reactivate(string $id): JsonResponse
    {
        $country = Country::findOrFail($id);
        $this->countryService->reactivate($country, auth('admin')->id());

        return response()->json(['success' => true, 'message' => "{$country->name_en} reactivated."]);
    }

    public function destroy(string $id): JsonResponse
    {
        $country = Country::findOrFail($id);

        if (!$this->countryService->canDelete($country)) {
            return response()->json([
                'message' => 'This country has existing orders and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $country->delete();

        return response()->json(['success' => true, 'message' => "{$country->name_en} deleted."]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shipping Settings
    // ─────────────────────────────────────────────────────────────────────────

    public function updateShippingSettings(Request $request, string $id): JsonResponse
    {
        $country = Country::findOrFail($id);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'settings.*.is_active' => ['boolean'],
            'settings.*.free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($country, $data) {
            foreach ($data['settings'] as $row) {
                CountryShippingSetting::updateOrCreate(
                    [
                        'country_id' => $country->id,
                        'shipping_method_id' => $row['shipping_method_id'],
                    ],
                    [
                        'is_active' => (bool) ($row['is_active'] ?? false),
                        'free_shipping_threshold' => $row['free_shipping_threshold'] ?? null,
                    ]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Shipping settings saved.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Category overrides
    // ─────────────────────────────────────────────────────────────────────────

    public function categoryOverridesDatatable(Request $request, string $id): JsonResponse
    {
        $country = Country::findOrFail($id);

        $columns = [
            [
                'title' => 'Category',
                'data' => 'name_en',
                'name' => 'name_en',
                'searchable_columns' => ['c.name_en', 'c.name_ar']
            ],
            [
                'title' => 'Available',
                'data' => 'is_available',
                'name' => 'is_available',
                'orderable_column' => 'cc.is_available',
                'searchable' => false
            ],
            [
                'title' => 'FBP %',
                'data' => 'commission_fbp_pct',
                'name' => 'commission_fbp_pct',
                'orderable_column' => 'cc.commission_fbp_pct',
                'searchable' => false
            ],
            [
                'title' => 'FBP Fixed',
                'data' => 'commission_fbp_fixed',
                'name' => 'commission_fbp_fixed',
                'orderable_column' => 'cc.commission_fbp_fixed',
                'searchable' => false
            ],
            [
                'title' => 'FBN %',
                'data' => 'commission_fbn_pct',
                'name' => 'commission_fbn_pct',
                'orderable_column' => 'cc.commission_fbn_pct',
                'searchable' => false
            ],
            [
                'title' => 'FBN Fixed',
                'data' => 'commission_fbn_fixed',
                'name' => 'commission_fbn_fixed',
                'orderable_column' => 'cc.commission_fbn_fixed',
                'searchable' => false
            ],
            [
                'title' => 'Override Reason',
                'data' => 'unavailable_reason',
                'name' => 'unavailable_reason',
                'orderable' => false,
                'searchable' => false
            ],
        ];

        $query = Category::query()->from('categories as c')
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->leftJoin('country_categories as cc', function ($join) use ($country) {
                $join->on('cc.category_id', '=', 'c.id')
                    ->where('cc.country_id', '=', $country->id);
            })
            ->whereNull('c.deleted_at')
            ->select([
                'c.id',
                'c.name_en',
                'c.name_ar',
                'cc.id as override_id',
                DB::raw('COALESCE(cc.is_available, true) as is_available'),
                DB::raw('COALESCE(cc.commission_fbp_pct, c.commission_fbp_pct) as commission_fbp_pct'),
                DB::raw('COALESCE(cc.commission_fbp_fixed, c.commission_fbp_fixed) as commission_fbp_fixed'),
                DB::raw('COALESCE(cc.commission_fbn_pct, c.commission_fbn_pct) as commission_fbn_pct'),
                DB::raw('COALESCE(cc.commission_fbn_fixed, c.commission_fbn_fixed) as commission_fbn_fixed'),
                'cc.commission_fbp_pct as override_commission_fbp_pct',
                'cc.commission_fbp_fixed as override_commission_fbp_fixed',
                'cc.commission_fbn_pct as override_commission_fbn_pct',
                'cc.commission_fbn_fixed as override_commission_fbn_fixed',
                'cc.unavailable_reason',
                'cc.notes',
            ]);

        return $this->dataTableResponse($request, $query, $columns, fn($row) => [
            'id' => $row->id,
            'override_id' => $row->override_id,
            'name_en' => $row->name_en,
            'name_ar' => $row->name_ar,
            'is_available' => (bool) $row->is_available,
            'commission_fbp_pct' => $row->commission_fbp_pct,
            'commission_fbp_fixed' => $row->commission_fbp_fixed,
            'commission_fbn_pct' => $row->commission_fbn_pct,
            'commission_fbn_fixed' => $row->commission_fbn_fixed,
            'override_commission_fbp_pct' => $row->override_commission_fbp_pct,
            'override_commission_fbp_fixed' => $row->override_commission_fbp_fixed,
            'override_commission_fbn_pct' => $row->override_commission_fbn_pct,
            'override_commission_fbn_fixed' => $row->override_commission_fbn_fixed,
            'unavailable_reason' => $row->unavailable_reason,
            'notes' => $row->notes,
            'country_id' => $country->id,
        ]);
    }

    public function updateCategoryOverrides(Request $request, string $id): JsonResponse
    {
        $country = Country::findOrFail($id);

        $data = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*.category_id' => ['required', 'exists:categories,id'],
            'overrides.*.is_available' => ['boolean'],
            'overrides.*.commission_fbp_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'overrides.*.commission_fbp_fixed' => ['nullable', 'integer', 'min:0'],
            'overrides.*.commission_fbn_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'overrides.*.commission_fbn_fixed' => ['nullable', 'integer', 'min:0'],
            'overrides.*.unavailable_reason' => ['nullable', 'string', 'max:100'],
            'overrides.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($country, $data) {
            foreach ($data['overrides'] as $row) {
                CountryCategory::updateOrCreate(
                    [
                        'country_id' => $country->id,
                        'category_id' => $row['category_id'],
                    ],
                    [
                        'is_available' => (bool) ($row['is_available'] ?? true),
                        'commission_fbp_pct' => $row['commission_fbp_pct'] ?? null,
                        'commission_fbp_fixed' => $row['commission_fbp_fixed'] ?? null,
                        'commission_fbn_pct' => $row['commission_fbn_pct'] ?? null,
                        'commission_fbn_fixed' => $row['commission_fbn_fixed'] ?? null,
                        'unavailable_reason' => $row['unavailable_reason'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'updated_by_admin_id' => auth('admin')->id(),
                    ]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Category overrides saved.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function timezoneList(): array
    {
        $zones = [];
        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            $zones[$tz] = $tz;
        }
        return $zones;
    }

    private function localeList(): array
    {
        return [
            'en' => 'English',
            'ar' => 'Arabic',
            'fr' => 'French',
            'tr' => 'Turkish',
        ];
    }

    private function columnDefinitions(): array
    {
        return [
            [
                'title' => 'Country',
                'data' => 'name',
                'name' => 'name',
                'orderable_column' => 'countries.name_en',
                'searchable_columns' => ['countries.name_en', 'countries.name_ar', 'countries.iso_code_2'],
            ],
            [
                'title' => 'Code',
                'data' => 'iso_code_2',
                'name' => 'iso_code_2',
                'orderable_column' => 'countries.iso_code_2',
                'searchable_columns' => ['countries.iso_code_2'],
            ],
            [
                'title' => 'Currency',
                'data' => 'currency_code',
                'name' => 'currency_code',
                'orderable' => false,
                'searchable' => false,
            ],
            [
                'title' => 'VAT',
                'data' => 'vat_rate',
                'name' => 'vat_rate',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
            ],
            [
                'title' => 'Cities',
                'data' => 'cities_count',
                'name' => 'cities_count',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
            ],
            [
                'title' => 'Payment',
                'data' => 'active_payment_gateways',
                'name' => 'active_payment_gateways',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
            ],
            [
                'title' => 'COD',
                'data' => 'cod_available',
                'name' => 'cod_available',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
                'render' => 'function(data){return data?\'<span class="text-success-600 font-bold">✓</span>\':\'<span class="text-gray-300">—</span>\';}',
            ],
            [
                'title' => 'Status',
                'data' => 'is_launched',
                'name' => 'is_launched',
                'orderable_column' => 'countries.is_launched',
                'searchable' => false,
                'render' => 'Renderers.badge({
                    true:  { label: "Launched",  color: "success" },
                    false: { label: "Draft",     color: "gray"    }
                })',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-right',
                'render' => 'Renderers.actions([
                    { type: "link", label: "Configure", url: ":edit_url", class: "btn-primary btn-sm" }
                ])',
            ],
        ];
    }
}
