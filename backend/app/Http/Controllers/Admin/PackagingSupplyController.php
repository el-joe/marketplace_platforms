<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PackagingSupply;
use App\Models\PackagingSupplyCountry;
use App\Models\PackagingSupplyRequest;
use App\Models\PackagingSupplyType;
use App\Models\Vendor;
use App\Notifications\Vendor\PackagingOrderApproved;
use App\Notifications\Vendor\PackagingOrderDelivered;
use App\Notifications\Vendor\PackagingOrderRejected;
use App\Notifications\Vendor\PackagingOrderShipped;
use App\Support\Money;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackagingSupplyController extends Controller
{
    use HasDataTable;
    use HasExport;

    /** Country rows shown inline in the catalog list before collapsing into "+N more". */
    private const PRICING_ROWS_SHOWN = 3;

    /** Attributes the edit modal needs from a catalog row. */
    private const EDITABLE_ATTRIBUTES = [
        'id', 'name_en', 'name_ar', 'packaging_supply_type_id', 'size',
        'description_en', 'description_ar', 'sort_order', 'is_active', 'image_path',
    ];

    // ── Catalog ────────────────────────────────────────────────────────────

    public function catalog(): View
    {
        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'currency_code', 'iso_code_2']);
        $types = PackagingSupplyType::ordered()->get();

        return view('admin.packaging.catalog', compact('countries', 'types'));
    }

    public function datatableCatalog(Request $request): JsonResponse
    {
        $countryFilter = $request->filled('country_id') && is_string($request->input('country_id'))
            ? $request->input('country_id')
            : null;

        $query = PackagingSupply::with([
            'supplyType',
            'countryPricing' => fn ($q) => $q->when($countryFilter, fn ($qq) => $qq->where('country_id', $countryFilter)),
            'countryPricing.country:id,name_en,name_ar,iso_code_2,currency_code',
        ]);

        $query = $this->applyFilters($query, $request, [
            'type_id' => fn ($q, $v) => $q->where('packaging_supply_type_id', $v),
            'is_active' => fn ($q, $v) => $q->where('is_active', (bool) $v),
            'country_id' => fn ($q, $v) => $q->whereHas('countryPricing', fn ($cq) => $cq->where('country_id', $v)),
        ]);

        $columns = [
            ['searchable_columns' => [], 'orderable_column' => null], // image
            ['searchable_columns' => ['name_en', 'name_ar'], 'orderable_column' => 'name_en'],
            ['searchable_columns' => ['type'], 'orderable_column' => 'type'],
            ['searchable_columns' => ['size'], 'orderable_column' => 'size'],
            ['searchable_columns' => [], 'orderable_column' => null], // price · stock by country
            ['searchable_columns' => [], 'orderable_column' => 'is_active'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        if (! $query->getQuery()->orders) {
            $query->orderBy('sort_order')->orderBy('name_en');
        }

        return $this->dataTableResponse($request, $query, $columns, function (PackagingSupply $row) {
            $imgHtml = $row->image_path
                ? '<img src="'.e(Storage::disk('public')->url($row->image_path)).'" class="w-10 h-10 object-cover rounded-lg">'
                : '<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300 text-xs">—</div>';

            $nameHtml = '<div class="min-w-0">'
                .'<div class="font-medium text-gray-900 truncate max-w-xs">'.e($row->name_en).'</div>'
                .'<div class="text-xs text-gray-400 truncate max-w-xs" dir="rtl">'.e($row->name_ar).'</div>'
                .'</div>';

            $typeBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium '.$row->typeBadgeClass().'">'.e($row->typeName()).'</span>';

            $statusToggle = '<button type="button" class="js-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none '
                .($row->is_active ? 'bg-emerald-500' : 'bg-gray-200').'" data-id="'.$row->id.'" data-active="'.($row->is_active ? '1' : '0').'">'
                .'<span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform '
                .($row->is_active ? 'translate-x-4' : 'translate-x-0.5').'"></span></button>';

            $actionsHtml = '<div class="flex justify-end gap-3">'
                .'<button type="button" class="js-edit-item text-primary-600 hover:underline text-xs font-medium" data-item="'.e(json_encode($row->only(self::EDITABLE_ATTRIBUTES))).'">'.e(__('admin.packaging_catalog.edit')).'</button>'
                .'<button type="button" class="js-delete-item text-red-500 hover:underline text-xs font-medium" data-id="'.$row->id.'" data-name="'.e(localized($row)).'">'.e(__('admin.packaging_catalog.delete')).'</button>'
                .'</div>';

            return [
                'image' => $imgHtml,
                'name' => $nameHtml,
                'type' => $typeBadge,
                'size' => e($row->size ?? '—'),
                'pricing' => $this->countryPricingHtml($row),
                'status' => $statusToggle,
                'actions' => $actionsHtml,
            ];
        });
    }

    /**
     * Client item #29: the catalog's legacy unit_cost/stock_available columns are never written by the
     * item form (pricing lives per country), so the list showed "Free"/"∞". Render the per-country
     * values instead, e.g. "OM 0.50 OMR · 100", collapsing extra countries into "+N more" with a tooltip.
     */
    private function countryPricingHtml(PackagingSupply $supply): string
    {
        $rows = $supply->countryPricing
            ->sortBy(fn (PackagingSupplyCountry $p) => $p->country?->iso_code_2 ?? '')
            ->values();

        if ($rows->isEmpty()) {
            return '<span class="text-xs text-gray-400">'.e(__('admin.packaging_catalog.no_country_pricing')).'</span>';
        }

        $lines = $rows->map(function (PackagingSupplyCountry $pricing) {
            $currency = $pricing->country?->currency_code ?? '';
            $cost = (float) $pricing->unit_cost > 0
                ? Money::format($pricing->unit_cost).' '.$currency
                : __('admin.packaging_catalog.free');
            $stock = $pricing->stock_available !== null
                ? number_format($pricing->stock_available)
                : __('admin.packaging_catalog.unlimited');

            return [
                'code' => $pricing->country?->iso_code_2 ?? '?',
                'country' => $pricing->country ? localized($pricing->country) : '',
                'text' => trim($cost).' · '.$stock,
                'active' => $pricing->is_active,
            ];
        });

        $renderLine = fn (array $line) => '<div class="whitespace-nowrap text-xs'.($line['active'] ? ' text-gray-800' : ' text-gray-400 line-through').'"'
            .' title="'.e($line['country'].($line['active'] ? '' : ' — '.__('admin.packaging_catalog.inactive_in_country'))).'">'
            .'<span class="font-semibold">'.e($line['code']).'</span> <span dir="ltr">'.e($line['text']).'</span></div>';

        $html = $lines->take(self::PRICING_ROWS_SHOWN)->map($renderLine)->implode('');

        $hidden = $lines->slice(self::PRICING_ROWS_SHOWN);
        if ($hidden->isNotEmpty()) {
            $tooltip = $hidden->map(fn (array $line) => $line['code'].' '.$line['text'])->implode("\n");
            $html .= '<div class="text-xs text-primary-600 cursor-help" title="'.e($tooltip).'">'
                .e(__('admin.packaging_catalog.more_countries', ['count' => $hidden->count()])).'</div>';
        }

        return '<div class="space-y-0.5">'.$html.'</div>';
    }

    public function getCatalogItem(PackagingSupply $supply): JsonResponse
    {
        $supply->load('countryPricing');

        $data = $supply->toArray();
        $data['country_pricing'] = $supply->countryPricing->map(fn (PackagingSupplyCountry $row) => [
            'country_id' => $row->country_id,
            'unit_cost' => $row->unit_cost,
            'stock_available' => $row->stock_available,
            'is_active' => $row->is_active,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * New selections must use an active type; an item may keep the (possibly now inactive) type it already has.
     */
    private function validateCatalogItem(Request $request, ?PackagingSupply $supply = null): array
    {
        $currentTypeId = $supply?->packaging_supply_type_id;

        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'packaging_supply_type_id' => [
                'required',
                'uuid',
                Rule::exists('packaging_supply_types', 'id')->where(function ($q) use ($currentTypeId) {
                    $q->where('is_active', true);
                    if ($currentTypeId) {
                        $q->orWhere('id', $currentTypeId);
                    }
                }),
            ],
            'size' => ['nullable', 'string', 'max:100'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'country_pricing' => ['required', 'array', 'min:1'],
            'country_pricing.*.country_id' => ['required', 'uuid', 'exists:countries,id'],
            'country_pricing.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'country_pricing.*.stock_available' => ['nullable', 'integer', 'min:0'],
            'country_pricing.*.is_active' => ['boolean'],
        ]);
    }

    public function storeCatalogItem(Request $request): JsonResponse
    {
        $data = $this->validateCatalogItem($request);
        $countryPricing = $data['country_pricing'];
        unset($data['country_pricing']);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        $supply = DB::transaction(function () use ($data, $countryPricing) {
            $supply = PackagingSupply::create($data);
            $this->syncCountryPricing($supply, $countryPricing);

            return $supply;
        });

        return response()->json([
            'success' => true,
            'message' => __('admin.packaging_catalog.created', ['name' => localized($supply)]),
            'data' => $supply,
        ]);
    }

    public function updateCatalogItem(Request $request, PackagingSupply $supply): JsonResponse
    {
        $data = $this->validateCatalogItem($request, $supply);
        $countryPricing = $data['country_pricing'];
        unset($data['country_pricing']);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($request->hasFile('image')) {
            if ($supply->image_path) {
                Storage::disk('public')->delete($supply->image_path);
            }
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        DB::transaction(function () use ($supply, $data, $countryPricing) {
            $supply->update($data);
            $this->syncCountryPricing($supply, $countryPricing);
        });

        return response()->json([
            'success' => true,
            'message' => __('admin.packaging_catalog.updated', ['name' => localized($supply)]),
            'data' => $supply,
        ]);
    }

    /**
     * Upsert the per-country pricing rows for a supply and remove any rows
     * for countries that are no longer present in the incoming set.
     */
    private function syncCountryPricing(PackagingSupply $supply, array $rows): void
    {
        $countryIds = [];

        foreach ($rows as $row) {
            $countryIds[] = $row['country_id'];

            PackagingSupplyCountry::updateOrCreate(
                [
                    'packaging_supply_id' => $supply->id,
                    'country_id' => $row['country_id'],
                ],
                [
                    'unit_cost' => $row['unit_cost'],
                    'stock_available' => $row['stock_available'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                ]
            );
        }

        $supply->countryPricing()
            ->whereNotIn('country_id', $countryIds)
            ->delete();
    }

    private function storeImage($file): string
    {
        $filename = (string) Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->storeAs('packaging', $filename, 'public');

        return 'packaging/'.$filename;
    }

    public function destroyCatalogItem(PackagingSupply $supply): JsonResponse
    {
        if ($supply->requestItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('admin.packaging_catalog.has_orders'),
            ], 422);
        }

        if ($supply->image_path) {
            Storage::disk('public')->delete($supply->image_path);
        }

        $supply->delete();

        return response()->json(['success' => true, 'message' => __('admin.packaging_catalog.deleted')]);
    }

    public function toggleActive(PackagingSupply $supply): JsonResponse
    {
        $supply->update(['is_active' => ! $supply->is_active]);

        return response()->json(['success' => true, 'is_active' => $supply->is_active]);
    }

    // ── Requests queue ─────────────────────────────────────────────────────

    public function requests(Request $request): View|StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportRequests($request);
        }

        $pending = PackagingSupplyRequest::where('status', 'pending')->count();
        $inTransit = PackagingSupplyRequest::whereIn('status', ['approved', 'shipped'])->count();
        $deliveredThisMonth = PackagingSupplyRequest::where('status', 'delivered')
            ->whereMonth('delivered_at', now()->month)
            ->whereYear('delivered_at', now()->year)
            ->count();

        $revenueThisMonth = PackagingSupplyRequest::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('currency, SUM(total_cost + delivery_fee) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'total' => Money::number($row->total)])
            ->values();

        $stats = [
            'pending' => $pending,
            'in_transit' => $inTransit,
            'delivered_this_month' => $deliveredThisMonth,
            'revenue_this_month' => $revenueThisMonth,
        ];

        $vendors = Vendor::orderBy('store_name')->get(['id', 'store_name']);

        return view('admin.packaging.requests', compact('stats', 'vendors'));
    }

    public function datatableRequests(Request $request): JsonResponse
    {
        $query = $this->buildRequestsQuery($request);

        $columns = [
            ['searchable_columns' => [], 'orderable_column' => 'request_number'], // searched below together with the vendor name
            ['searchable_columns' => [], 'orderable_column' => null], // vendor
            ['searchable_columns' => [], 'orderable_column' => 'status'],
            ['searchable_columns' => [], 'orderable_column' => null], // items count
            ['searchable_columns' => [], 'orderable_column' => 'total_cost'],
            ['searchable_columns' => [], 'orderable_column' => 'delivery_fee'],
            ['searchable_columns' => [], 'orderable_column' => null], // grand total
            ['searchable_columns' => [], 'orderable_column' => null], // currency
            ['searchable_columns' => [], 'orderable_column' => 'created_at'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        // Grouped so the search never ORs past the status/vendor/date filters (a top-level orWhereHas did).
        $searchValue = $request->input('search.value');
        if (is_string($searchValue) && $searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('request_number', 'like', '%'.$searchValue.'%')
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('store_name', 'like', '%'.$searchValue.'%'));
            });
        }

        if (! $query->getQuery()->orders) {
            $query->orderByRaw("status = 'pending' desc")->orderByDesc('created_at');
        }

        return $this->dataTableResponse($request, $query, $columns, function (PackagingSupplyRequest $row) {
            $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium '.$row->statusBadgeClass().'">'.e($row->status->label()).'</span>';

            $viewUrl = route('admin.packaging.requests.show', $row->id);
            $actionsHtml = '<a href="'.$viewUrl.'" class="text-primary-600 hover:underline text-xs font-medium">View</a>';

            return [
                'request_number' => e($row->request_number),
                'vendor' => e($row->vendor->store_name ?? '—'),
                'status' => $statusBadge,
                'items_count' => $row->items()->count(),
                'total_cost' => Money::format($row->total_cost),
                'delivery_fee' => Money::format($row->delivery_fee),
                'grand_total' => Money::format($row->grand_total),
                'currency' => $row->currency,
                'created_at' => admin_tz($row->created_at)?->format('d M Y'),
                'actions' => $actionsHtml,
            ];
        });
    }

    private function buildRequestsQuery(Request $request): Builder
    {
        $query = PackagingSupplyRequest::with('vendor');

        $query = $this->applyFilters($query, $request, [
            'search' => fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('request_number', 'like', '%'.$v.'%')
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('store_name', 'like', '%'.$v.'%'));
            }),
            'status' => fn ($q, $v) => $q->where('status', $v),
            'vendor_id' => fn ($q, $v) => $q->where('vendor_id', $v),
            'date_from' => fn ($q, $v) => $this->isDate($v) ? $q->where('created_at', '>=', admin_day_start($v)) : $q,
            'date_to' => fn ($q, $v) => $this->isDate($v) ? $q->where('created_at', '<=', admin_day_end($v)) : $q,
        ]);

        return $query;
    }

    /** A "Y-m-d" day as sent by the date-range filter; anything else is ignored instead of throwing. */
    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }

    private function exportRequests(Request $request): StreamedResponse
    {
        $requests = $this->buildRequestsQuery($request)->with('items')->orderByDesc('created_at')->get();

        $headers = ['Request #', 'Vendor', 'Items', 'Total', 'Currency', 'Status', 'Date'];

        $rows = $requests->map(fn ($req) => [
            $req->request_number,
            $req->vendor?->store_name,
            $req->items->count(),
            Money::format($req->grand_total),
            $req->currency,
            $req->status?->value,
            optional(admin_tz($req->created_at))->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('packaging_requests', $headers, $rows),
            'csv' => $this->exportCsv('packaging_requests', $headers, $rows),
            'word' => $this->exportWord('packaging_requests', 'Packaging Supply Requests', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function showRequest(PackagingSupplyRequest $request): View
    {
        $request->load(['items.supply', 'vendor', 'approvedBy']);

        return view('admin.packaging.show-request', ['req' => $request]);
    }

    public function approve(PackagingSupplyRequest $request): JsonResponse
    {
        if ($request->status->value !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request is not pending.'], 422);
        }

        DB::transaction(function () use ($request) {
            $request->update([
                'status' => 'approved',
                'approved_by_admin_id' => auth('admin')->id(),
                'approved_at' => now(),
            ]);

            // Stock was already decremented in packaging_supply_countries at submit time.
            Notification::send($request->vendor->vendorAdmins, new PackagingOrderApproved($request));
        });

        return response()->json(['success' => true, 'message' => 'Request approved.']);
    }

    public function reject(Request $httpRequest, PackagingSupplyRequest $request): JsonResponse
    {
        $httpRequest->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        if ($request->status->value !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request is not pending.'], 422);
        }

        $request->update([
            'status' => 'rejected',
            'notes' => $httpRequest->input('notes'),
        ]);

        Notification::send($request->vendor->vendorAdmins, new PackagingOrderRejected($request));

        return response()->json(['success' => true, 'message' => 'Request rejected.']);
    }

    public function markShipped(PackagingSupplyRequest $request): JsonResponse
    {
        if ($request->status->value !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Request must be approved first.'], 422);
        }

        $request->update(['status' => 'shipped', 'shipped_at' => now()]);

        Notification::send($request->vendor->vendorAdmins, new PackagingOrderShipped($request));

        return response()->json(['success' => true, 'message' => 'Request marked as shipped.']);
    }

    public function markDelivered(PackagingSupplyRequest $request): JsonResponse
    {
        if ($request->status->value !== 'shipped') {
            return response()->json(['success' => false, 'message' => 'Request must be shipped first.'], 422);
        }

        $request->update(['status' => 'delivered', 'delivered_at' => now()]);

        Notification::send($request->vendor->vendorAdmins, new PackagingOrderDelivered($request));

        return response()->json(['success' => true, 'message' => 'Request marked as delivered.']);
    }
}
