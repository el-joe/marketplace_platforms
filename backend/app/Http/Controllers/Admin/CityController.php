<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\ShippingZone;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CityController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Listing
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.cities.index', [
            'countries' => Country::where('is_active', true)->orderBy('name_en')->pluck('name_en', 'id'),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.cities')],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            [
                'title' => 'City',
                'data' => 'name',
                'name' => 'name',
                'orderable_column' => 'cities.name_en',
                'searchable_columns' => ['cities.name_en', 'cities.name_ar'],
            ],
            [
                'title' => 'Country',
                'data' => 'country_name',
                'name' => 'country_name',
                'orderable_column' => 'countries.name_en',
                'searchable_columns' => ['countries.name_en'],
            ],
            [
                'title' => 'Zone',
                'data' => 'shipping_zone',
                'name' => 'shipping_zone',
                'orderable' => false,
                'searchable' => false,
            ],
            [
                'title' => 'COD',
                'data' => 'cod_available',
                'name' => 'cod_available',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
            ],
            [
                'title' => 'Status',
                'data' => 'is_active',
                'name' => 'is_active',
                'orderable_column' => 'cities.is_active',
                'searchable' => false,
                'render' => 'Renderers.badge({
                    true:  { label: "Active",   color: "success" },
                    false: { label: "Inactive", color: "gray"    }
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
                    { type: "link", label: "Edit", url: ":edit_url", class: "btn-ghost btn-sm" }
                ])',
            ],
        ];

        $query = City::withTrashed()
            ->join('countries', 'countries.id', '=', 'cities.country_id')
            ->leftJoin('shipping_zones', 'shipping_zones.id', '=', 'cities.shipping_zone_id')
            ->select([
                'cities.id',
                'cities.name_en',
                'cities.name_ar',
                'cities.country_id',
                'countries.name_en as country_name_en',
                'cities.shipping_zone_id',
                'shipping_zones.name as shipping_zone_name',
                'cities.is_active',
                'cities.cod_available',
                'cities.latitude',
                'cities.longitude',
                'cities.deleted_at',
            ]);

        $query = $this->applyFilters($query, $request, [
            'country_id' => fn($q, $v) => $q->where('cities.country_id', $v),
            'is_active' => fn($q, $v) => $q->where('cities.is_active', (bool) $v),
            'no_zone' => fn($q, $v) => $v ? $q->whereNull('cities.shipping_zone_id') : $q,
        ]);

        return $this->dataTableResponse($request, $query, $columns, fn($row) => [
            'id' => $row->id,
            'name' => $row->name_en . ' / ' . $row->name_ar,
            'country_name' => $row->country_name_en,
            'shipping_zone' => $row->shipping_zone_name
                ? $row->shipping_zone_name
                : '<span class="text-warning-600 text-xs font-medium">No zone ⚠</span>',
            'cod_available' => (bool) $row->cod_available
                ? '<span class="text-success-600 font-bold">✓</span>'
                : '<span class="text-gray-300">—</span>',
            'is_active' => (bool) $row->is_active,
            'is_deleted' => !is_null($row->deleted_at),
            'edit_url' => route('admin.cities.edit', $row->id),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.cities.create', [
            'countries' => Country::where('is_active', true)->orderBy('name_en')->pluck('name_en', 'id'),
            'shippingZones' => ShippingZone::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'country_id']),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.cities'), 'url' => route('admin.cities.index')],
                ['label' => __('admin.cities_section.add_city')],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCity($request);

        $city = City::create(array_merge(['id' => (string) Str::uuid()], $data));

        return redirect()->route('admin.cities.edit', $city->id)
            ->with('success', __('admin.cities_section.created_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $id): View
    {
        $city = City::findOrFail($id);

        return view('admin.cities.edit', [
            'city' => $city,
            'countries' => Country::where('is_active', true)->orderBy('name_en')->pluck('name_en', 'id'),
            'shippingZones' => ShippingZone::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'country_id']),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.cities'), 'url' => route('admin.cities.index')],
                ['label' => $city->name_en],
            ],
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $city = City::findOrFail($id);

        $data = $this->validateCity($request, $id);
        $city->update($data);

        return back()->with('success', __('admin.cities_section.updated_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        $city = City::findOrFail($id);
        $city->delete();

        return response()->json(['success' => true, 'message' => __('admin.cities_section.deleted_msg', ['name' => $city->name_en])]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Import from CSV
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Expected CSV columns (in order):
     * name_en, name_ar, country_id (UUID) OR iso_code_2, latitude, longitude,
     * shipping_zone_id (optional), is_active (0/1, default 1), cod_available (0/1, default 0)
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return response()->json(['message' => __('admin.cities_section.could_not_open_file')], 422);
        }

        // Read and discard header row
        $header = fgetcsv($handle);

        // Pre-load country lookup by iso_code_2
        $countryByIso = Country::pluck('id', 'iso_code_2')->all();

        $inserted = 0;
        $errors = [];
        $row = 0;
        $now = now();

        DB::beginTransaction();
        try {
            while (($line = fgetcsv($handle)) !== false) {
                $row++;

                if (count($line) < 4) {
                    $errors[] = __('admin.cities_section.row_too_few_columns', ['row' => $row]);
                    continue;
                }

                [$nameEn, $nameAr, $countryRef, $lat, $lng] = array_pad($line, 5, null);
                $zoneId = isset($line[5]) && $line[5] !== '' ? $line[5] : null;
                $isActive = isset($line[6]) ? (bool) (int) $line[6] : true;
                $codAvailable = isset($line[7]) ? (bool) (int) $line[7] : false;

                // Resolve country reference (UUID or ISO-2)
                if (strlen((string) $countryRef) === 2) {
                    $countryId = $countryByIso[strtoupper($countryRef)] ?? null;
                } else {
                    $countryId = $countryRef;
                }

                if (!$countryId) {
                    $errors[] = __('admin.cities_section.row_unknown_country', ['row' => $row, 'country' => $countryRef]);
                    continue;
                }

                if (empty($nameEn)) {
                    $errors[] = __('admin.cities_section.row_name_required', ['row' => $row]);
                    continue;
                }

                City::query()->insert([
                    'id' => (string) Str::uuid(),
                    'country_id' => $countryId,
                    'name_en' => trim($nameEn),
                    'name_ar' => trim((string) $nameAr),
                    'latitude' => $lat !== '' ? (float) $lat : null,
                    'longitude' => $lng !== '' ? (float) $lng : null,
                    'shipping_zone_id' => $zoneId,
                    'is_active' => $isActive,
                    'cod_available' => $codAvailable,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $inserted++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json(['message' => __('admin.cities_section.import_failed', ['error' => $e->getMessage()])], 500);
        }

        fclose($handle);

        $message = count($errors)
            ? __('admin.cities_section.import_result_with_errors', ['count' => $inserted, 'errors' => count($errors)])
            : __('admin.cities_section.import_result', ['count' => $inserted]);

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
            'errors' => $errors,
            'message' => $message,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function validateCity(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'shipping_zone_id' => ['nullable', 'exists:shipping_zones,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
            'cod_available' => ['boolean'],
        ]);
    }
}
