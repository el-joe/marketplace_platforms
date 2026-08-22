<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PackagingSupplyRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\PackagingSupply;
use App\Models\PackagingSupplyCountry;
use App\Models\PackagingSupplyRequest;
use App\Models\PackagingSupplyRequestItem;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Notifications\Admin\NewPackagingOrderReceived;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class PackagingSupplyController extends Controller
{
    use HasDataTable;
    use HasExport;

    private function vendorCountryId(): ?string
    {
        $vendor = auth('vendor')->user();

        return $vendor?->vendor?->country_id;
    }

    public function index(): View
    {
        $countryId = $this->vendorCountryId();

        $supplies = PackagingSupply::where('is_active', true)
            ->whereHas('countryPricing', function ($q) use ($countryId) {
                $q->where('country_id', $countryId)->where('is_active', true);
            })
            ->with(['countryPricing' => function ($q) use ($countryId) {
                $q->where('country_id', $countryId)->where('is_active', true);
            }])
            ->orderBy('type')
            ->orderBy('name_en')
            ->get();

        return view('partner.packaging-supplies.index', compact('supplies'));
    }

    public function request(): View
    {
        $vendor = auth('vendor')->user();
        $countryId = $this->vendorCountryId();

        $supplies = PackagingSupply::where('is_active', true)
            ->whereHas('countryPricing', function ($q) use ($countryId) {
                $q->where('country_id', $countryId)->where('is_active', true);
            })
            ->with(['countryPricing' => function ($q) use ($countryId) {
                $q->where('country_id', $countryId)->where('is_active', true);
            }])
            ->orderBy('type')
            ->orderBy('name_en')
            ->get();

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('partner.packaging-supplies.request', compact('supplies', 'warehouses'));
    }

    public function submitRequest(Request $request): RedirectResponse
    {
        $vendor = auth('vendor')->user();
        $countryId = $this->vendorCountryId();

        $data = $request->validate([
            'warehouse_id'          => ['nullable', 'uuid', 'exists:warehouses,id'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.supply_id'     => ['required', 'uuid', 'exists:packaging_supplies,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        // Load requested supplies and their pricing for the vendor's country
        $supplyIds = collect($data['items'])->pluck('supply_id')->unique();
        $supplies  = PackagingSupply::whereIn('id', $supplyIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $countryPricing = PackagingSupplyCountry::whereIn('packaging_supply_id', $supplyIds)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->get()
            ->keyBy('packaging_supply_id');

        // Reject if any supply is unavailable in the vendor's country
        foreach ($data['items'] as $item) {
            abort_unless($supplies->has($item['supply_id']), 422, 'One or more selected supplies are unavailable.');
            abort_unless($countryPricing->has($item['supply_id']), 422, 'One or more selected supplies are unavailable in your country.');
        }

        $totalCost = 0;
        $lineItems = [];
        $currency  = auth('vendor')->user()?->vendor?->country?->currency_code;

        foreach ($data['items'] as $item) {
            $supply = $supplies[$item['supply_id']];
            $pricing = $countryPricing[$item['supply_id']];

            if ($pricing->stock_available !== null) {
                abort_if($pricing->stock_available < $item['quantity'], 422, __('partner.packaging_supplies.messages.insufficient_stock', ['name' => $supply->name_en]));
            }

            $lineTotal = $pricing->unit_cost * $item['quantity'];
            $totalCost += $lineTotal;

            $lineItems[] = [
                'packaging_supply_id' => $supply->id,
                'quantity'            => $item['quantity'],
                'unit_cost'           => $pricing->unit_cost,
                'line_total'          => $lineTotal,
                'created_at'          => now(),
            ];
        }

        $supplyRequest = DB::transaction(function () use ($vendor, $data, $lineItems, $totalCost, $currency) {
            $supplyRequest = PackagingSupplyRequest::create([
                'request_number'     => PackagingSupplyRequest::generateRequestNumber(),
                'vendor_id'          => $vendor->vendor_id,
                'warehouse_id'       => $data['warehouse_id'] ?? null,
                'status'             => PackagingSupplyRequestStatus::Pending,
                'total_cost'   => $totalCost,
                'delivery_fee' => $this->resolveDeliveryFeeCents($vendor->vendor),
                'currency'           => $currency,
                'notes'              => $data['notes'] ?? null,
            ]);

            foreach ($lineItems as $line) {
                $supplyRequest->items()->create($line);

                PackagingSupplyCountry::where('packaging_supply_id', $line['packaging_supply_id'])
                    ->where('country_id', $supplyRequest->vendor->country_id)
                    ->whereNotNull('stock_available')
                    ->decrement('stock_available', $line['quantity']);
            }

            return $supplyRequest;
        });

        Notification::send(Admin::permission('packaging.manage')->get(), new NewPackagingOrderReceived($supplyRequest));

        return redirect()
            ->route('partner.packaging-supplies.my-requests')
            ->with('success', __('partner.packaging_supplies.messages.submitted_with_number', ['number' => $supplyRequest->request_number]));
    }

    public function myRequests(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportMyRequests($request);
        }

        $vendor = auth('vendor')->user();

        $supplyRequests = $this->buildMyRequestsQuery($request)
            ->with('items.supply')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('partner.packaging-supplies.my-requests', compact('supplyRequests'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildMyRequestsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $vendor = auth('vendor')->user();

        $query = PackagingSupplyRequest::where('vendor_id', $vendor->vendor_id);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('status', $v),
            'date_from' => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);

        return $query;
    }

    private function exportMyRequests(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $requests = $this->buildMyRequestsQuery($request)
            ->withCount('items')
            ->latest()
            ->get();

        $headers = ['Request #', 'Items', 'Total', 'Currency', 'Status', 'Date'];

        $rows = $requests->map(fn($row) => [
            $row->request_number,
            $row->items_count,
            number_format($row->total_cost),
            $row->currency,
            $row->status instanceof PackagingSupplyRequestStatus ? $row->status->value : $row->status,
            $row->created_at->format('Y-m-d H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('packaging-supply-requests', $headers, $rows),
            'csv' => $this->exportCsv('packaging-supply-requests', $headers, $rows),
            'word' => $this->exportWord('packaging-supply-requests', 'Packaging Supply Requests', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    public function showRequest(PackagingSupplyRequest $packagingSupplyRequest): View
    {
        $vendor = auth('vendor')->user();

        abort_unless($packagingSupplyRequest->vendor_id === $vendor->vendor_id, 403);

        $packagingSupplyRequest->load(['items.supply', 'warehouse']);

        return view('partner.packaging-supplies.show-request', ['req' => $packagingSupplyRequest]);
    }

    public function deliveryFee(): JsonResponse
    {
        $vendor = auth('vendor')->user();
        $feeCents = $this->resolveDeliveryFeeCents($vendor->vendor);
        $currency = $vendor?->vendor?->country?->currency_code ?? config('app.currency', 'SAR');

        return response()->json([
            'fee'      => $feeCents,
            'currency' => $currency,
        ]);
    }

    private function resolveDeliveryFeeCents(?\App\Models\Vendor $vendor): int
    {
        $feesByCountry = Setting::get('packaging_delivery_fee', []);

        if (!is_array($feesByCountry) || !$vendor || !$vendor->country_id) {
            return 0;
        }

        return (int) ($feesByCountry[$vendor->country_id] ?? 0);
    }
}
