<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PackagingSupply;
use App\Models\PackagingSupplyCountry;
use App\Models\PackagingSupplyRequest;
use App\Models\Vendor;
use App\Notifications\Vendor\PackagingOrderApproved;
use App\Notifications\Vendor\PackagingOrderDelivered;
use App\Notifications\Vendor\PackagingOrderRejected;
use App\Notifications\Vendor\PackagingOrderShipped;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PackagingSupplyController extends Controller
{
    use HasDataTable;
    use HasExport;

    private const CURRENCIES = ['AED', 'SAR', 'EGP', 'KWD', 'OMR', 'QAR', 'BHD', 'JOD'];
    private const TYPES = ['box', 'bag', 'tape', 'label', 'bubble_wrap', 'other'];

    // ── Catalog ────────────────────────────────────────────────────────────

    public function catalog(): View
    {
        $supplies = PackagingSupply::orderBy('sort_order')->orderBy('name_en')->get();
        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'currency_code']);

        return view('admin.packaging.catalog', compact('supplies', 'countries'));
    }

    public function datatableCatalog(Request $request): JsonResponse
    {
        $query = PackagingSupply::withCount('countryPricing as country_count');

        $query = $this->applyFilters($query, $request, [
            'type' => fn ($q, $v) => $q->where('type', $v),
            'is_active' => fn ($q, $v) => $q->where('is_active', (bool) $v),
        ]);

        $columns = [
            ['searchable_columns' => [], 'orderable_column' => null], // image
            ['searchable_columns' => ['name_en', 'name_ar'], 'orderable_column' => 'name_en'],
            ['searchable_columns' => ['type'], 'orderable_column' => 'type'],
            ['searchable_columns' => ['size'], 'orderable_column' => 'size'],
            ['searchable_columns' => [], 'orderable_column' => 'unit_cost'],
            ['searchable_columns' => [], 'orderable_column' => 'stock_available'],
            ['searchable_columns' => [], 'orderable_column' => 'is_active'],
            ['searchable_columns' => [], 'orderable_column' => null], // countries
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        if (!$query->getQuery()->orders) {
            $query->orderBy('sort_order')->orderBy('name_en');
        }

        return $this->dataTableResponse($request, $query, $columns, function (PackagingSupply $row) {
            $imgHtml = $row->image_path
                ? '<img src="' . e(Storage::disk('public')->url($row->image_path)) . '" class="w-10 h-10 object-cover rounded-lg">'
                : '<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300 text-xs">—</div>';

            $nameHtml = '<div class="min-w-0">'
                . '<div class="font-medium text-gray-900 truncate max-w-xs">' . e($row->name_en) . '</div>'
                . '<div class="text-xs text-gray-400 truncate max-w-xs" dir="rtl">' . e($row->name_ar) . '</div>'
                . '</div>';

            $typeBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $row->typeBadgeClass() . '">' . e($row->type->label()) . '</span>';

            $stockHtml = $row->stock_available !== null ? number_format($row->stock_available) : '&infin;';

            $statusToggle = '<button type="button" class="js-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none '
                . ($row->is_active ? 'bg-emerald-500' : 'bg-gray-200') . '" data-id="' . $row->id . '" data-active="' . ($row->is_active ? '1' : '0') . '">'
                . '<span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform '
                . ($row->is_active ? 'translate-x-4' : 'translate-x-0.5') . '"></span></button>';

            $actionsHtml = '<div class="flex justify-end gap-3">'
                . '<button type="button" class="js-edit-item text-primary-600 hover:underline text-xs font-medium" data-item="' . e(json_encode($row)) . '">Edit</button>'
                . '<button type="button" class="js-delete-item text-red-500 hover:underline text-xs font-medium" data-id="' . $row->id . '" data-name="' . e($row->name_en) . '">Delete</button>'
                . '</div>';

            $countriesHtml = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">'
                . (int) $row->country_count . '</span>';

            return [
                'image' => $imgHtml,
                'name' => $nameHtml,
                'type' => $typeBadge,
                'size' => $row->size ?? '—',
                'unit_cost' => $row->unit_cost_formatted,
                'stock' => $stockHtml,
                'status' => $statusToggle,
                'countries' => $countriesHtml,
                'actions' => $actionsHtml,
            ];
        });
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

    private function validateCatalogItem(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:' . implode(',', self::TYPES)],
            'size' => ['nullable', 'string', 'max:100'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'country_pricing' => ['required', 'array', 'min:1'],
            'country_pricing.*.country_id' => ['required', 'uuid', 'exists:countries,id'],
            'country_pricing.*.unit_cost' => ['required', 'integer', 'min:0'],
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
            'message' => "Supply \"{$supply->name_en}\" created.",
            'data' => $supply,
        ]);
    }

    public function updateCatalogItem(Request $request, PackagingSupply $supply): JsonResponse
    {
        $data = $this->validateCatalogItem($request);
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
            'message' => "Supply \"{$supply->name_en}\" updated.",
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
        $filename = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('packaging', $filename, 'public');

        return 'packaging/' . $filename;
    }

    public function destroyCatalogItem(PackagingSupply $supply): JsonResponse
    {
        if ($supply->requestItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This item has existing orders and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        if ($supply->image_path) {
            Storage::disk('public')->delete($supply->image_path);
        }

        $supply->delete();

        return response()->json(['success' => true, 'message' => 'Supply deleted.']);
    }

    public function toggleActive(PackagingSupply $supply): JsonResponse
    {
        $supply->update(['is_active' => !$supply->is_active]);

        return response()->json(['success' => true, 'is_active' => $supply->is_active]);
    }

    // ── Requests queue ─────────────────────────────────────────────────────

    public function requests(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
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
            ->map(fn ($row) => ['currency' => $row->currency, 'total' => (int) $row->total])
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
            ['searchable_columns' => ['request_number'], 'orderable_column' => 'request_number'],
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

        $searchValue = $request->input('search.value');
        if (!empty($searchValue)) {
            $query->orWhereHas('vendor', function ($q) use ($searchValue) {
                $q->where('store_name', 'like', '%' . $searchValue . '%');
            });
        }

        if (!$query->getQuery()->orders) {
            $query->orderByRaw("status = 'pending' desc")->orderByDesc('created_at');
        }

        return $this->dataTableResponse($request, $query, $columns, function (PackagingSupplyRequest $row) {
            $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $row->statusBadgeClass() . '">' . e($row->status->label()) . '</span>';

            $viewUrl = route('admin.packaging.requests.show', $row->id);
            $actionsHtml = '<a href="' . $viewUrl . '" class="text-primary-600 hover:underline text-xs font-medium">View</a>';

            return [
                'request_number' => e($row->request_number),
                'vendor' => e($row->vendor->store_name ?? '—'),
                'status' => $statusBadge,
                'items_count' => $row->items()->count(),
                'total_cost' => number_format($row->total_cost),
                'delivery_fee' => number_format($row->delivery_fee),
                'grand_total' => number_format($row->grand_total),
                'currency' => $row->currency,
                'created_at' => $row->created_at->format('d M Y'),
                'actions' => $actionsHtml,
            ];
        });
    }

    private function buildRequestsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = PackagingSupplyRequest::with('vendor');

        $query = $this->applyFilters($query, $request, [
            'search' => fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('request_number', 'like', '%' . $v . '%')
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('store_name', 'like', '%' . $v . '%'));
            }),
            'status' => fn ($q, $v) => $q->where('status', $v),
            'vendor_id' => fn ($q, $v) => $q->where('vendor_id', $v),
            'date_from' => fn ($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);

        return $query;
    }

    private function exportRequests(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $requests = $this->buildRequestsQuery($request)->with('items')->orderByDesc('created_at')->get();

        $headers = ['Request #', 'Vendor', 'Items', 'Total', 'Currency', 'Status', 'Date'];

        $rows = $requests->map(fn ($req) => [
            $req->request_number,
            $req->vendor?->store_name,
            $req->items->count(),
            number_format($req->grand_total),
            $req->currency,
            $req->status?->value,
            optional($req->created_at)->format('d M Y H:i'),
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
