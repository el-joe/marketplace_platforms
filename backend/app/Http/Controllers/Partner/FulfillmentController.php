<?php

namespace App\Http\Controllers\Partner;

use App\Enums\GlobalSystemType;
use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Models\FbnInboundRequest;
use App\Models\FbnStorageFee;
use App\Models\Admin;
use App\Models\MarketplaceShippingRule;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Notifications\Admin\InboundTrackingAddedNotification;
use App\Services\ActivityLoggerService;
use App\Services\WarehouseVendorLimitService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class FulfillmentController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(private readonly WarehouseVendorLimitService $limitService) {}

    private function vendor()
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    // ── Overview ──────────────────────────────────────────────────────────────

    public function index(): View
    {
        $vendor = $this->vendor();

        $listings = VendorListing::where('vendor_id', $vendor->id)
            ->with(['productVariant.product', 'warehouseInventories.warehouse'])
            ->get();

        $stats = [
            'fbn_count' => $listings->where('global_system_type', GlobalSystemType::ExpressFbn)->count(),
            'fbp_count' => $listings->where('global_system_type', GlobalSystemType::MerchantFbp)->count(),
            'marketplace_count' => $listings->where('global_system_type', GlobalSystemType::Marketplace)->count(),
            'pending_requests' => FbnInboundRequest::where('vendor_id', $vendor->id)
                ->whereIn('status', ['draft', 'submitted', 'approved'])
                ->count(),
        ];

        $fbnListings = $listings->where('global_system_type', GlobalSystemType::ExpressFbn)->values();
        $fbpListings = $listings->where('global_system_type', GlobalSystemType::MerchantFbp)->values();
        $marketplaceListings = $listings->where('global_system_type', GlobalSystemType::Marketplace)->values();

        $warehouses = Warehouse::where('is_active', true)
            ->where(function ($q) use ($vendor) {
                $q->where('type', WarehouseType::PlatformFbn->value)
                    ->orWhere('owner_vendor_id', $vendor->id);
            })
            ->get(['id', 'name', 'code']);

        return view('partner.fulfillment.index', compact(
            'vendor',
            'stats',
            'fbnListings',
            'fbpListings',
            'marketplaceListings',
            'warehouses'
        ));
    }

    // ── FBN: list vendor's inbound requests ───────────────────────────────────

    public function fbnRequests(): JsonResponse
    {
        $vendor = $this->vendor();
        $requests = FbnInboundRequest::where('vendor_id', $vendor->id)
            ->with(['warehouse', 'vendorListing.productVariant.product'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'request_number' => $r->request_number,
                    'product' => $r->vendorListing?->productVariant?->product?->name_en ?? '—',
                    'warehouse' => $r->warehouse?->name ?? '—',
                    'qty_requested' => $r->quantity_requested,
                    'qty_received' => $r->quantity_received,
                    'status' => $r->status->value,
                    'status_color' => $r->statusColor(),
                    'status_label' => $r->statusLabel(),
                    'expected_arrival' => $r->expected_arrival?->format('d M Y'),
                    'tracking_number' => $r->tracking_number,
                    'can_cancel' => $r->canBeCancelled(),
                    'created_at' => $r->created_at->format('d M Y'),
                ];
            });

        return response()->json(['success' => true, 'data' => $requests]);
    }

    // ── FBN: submit inbound request ───────────────────────────────────────────

    public function submitInboundRequest(Request $request): JsonResponse
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'vendor_listing_id' => 'required|exists:vendor_listings,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity_requested' => 'required|integer|min:1|max:10000',
            'vendor_notes' => 'nullable|string|max:1000',
        ]);

        // Verify listing belongs to this vendor
        $listing = VendorListing::where('id', $data['vendor_listing_id'])
            ->where('vendor_id', $vendor->id)
            ->where('global_system_type', GlobalSystemType::ExpressFbn)
            ->firstOrFail();

        $this->limitService->assertWithinLimit($data['warehouse_id'], $listing, $data['quantity_requested']);

        $req = FbnInboundRequest::create([
            'vendor_id' => $vendor->id,
            'vendor_listing_id' => $listing->id,
            'warehouse_id' => $data['warehouse_id'],
            'quantity_requested' => $data['quantity_requested'],
            'status' => 'submitted',
            'vendor_notes' => $data['vendor_notes'] ?? null,
        ]);

        // Mark quantity_inbound in warehouse inventory
        WarehouseInventory::updateOrCreate(
            ['vendor_listing_id' => $listing->id, 'warehouse_id' => $data['warehouse_id']],
            []
        )->increment('quantity_inbound', $data['quantity_requested']);

        return response()->json([
            'success' => true,
            'message' => __('partner.fulfillment.messages.inbound_request_submitted', ['number' => $req->request_number]),
        ]);
    }

    // ── FBN: cancel inbound request ───────────────────────────────────────────

    public function cancelInboundRequest(Request $request, FbnInboundRequest $inboundRequest): JsonResponse
    {
        $vendor = $this->vendor();

        if ($inboundRequest->vendor_id !== $vendor->id) {
            abort(403);
        }

        if (!$inboundRequest->canBeCancelled()) {
            return response()->json(['success' => false, 'message' => __('partner.fulfillment.messages.cannot_cancel_current_state')], 422);
        }

        // Reverse quantity_inbound
        WarehouseInventory::where('vendor_listing_id', $inboundRequest->vendor_listing_id)
            ->where('warehouse_id', $inboundRequest->warehouse_id)
            ->where('quantity_inbound', '>', 0)
            ->each(fn($inv) => $inv->decrement('quantity_inbound', min($inboundRequest->quantity_requested, $inv->quantity_inbound)));

        $inboundRequest->update(['status' => 'rejected', 'rejection_reason' => __('partner.fulfillment.messages.cancelled_by_vendor')]);

        return response()->json(['success' => true, 'message' => __('partner.fulfillment.messages.inbound_request_cancelled')]);
    }

    // ── FBN: vendor adds tracking number ──────────────────────────────────────

    public function updateInboundTracking(Request $request, FbnInboundRequest $inboundRequest): JsonResponse
    {
        $vendor = $this->vendor();

        if ($inboundRequest->vendor_id !== $vendor->id) {
            abort(403);
        }

        if (!in_array($inboundRequest->status->value, ['approved', 'submitted'], true)) {
            return response()->json(['success' => false, 'message' => __('partner.fulfillment.messages.cannot_update_tracking_stage')], 422);
        }

        $data = $request->validate([
            'tracking_number' => 'required|string|max:100',
        ]);

        DB::transaction(function () use ($inboundRequest, $data) {
            $inboundRequest->update(['tracking_number' => $data['tracking_number']]);

            app(ActivityLoggerService::class)->log(
                'Tracking number added to inbound request ' . $inboundRequest->request_number,
                $inboundRequest,
                Auth::guard('vendor')->user(),
                ['tracking_number' => $data['tracking_number']],
                'fulfillment',
                'updated'
            );

            try {
                Notification::send(
                    Admin::where('status', 'active')->get(),
                    new InboundTrackingAddedNotification($inboundRequest),
                );
            } catch (\Throwable $e) {
                Log::warning('InboundTrackingAddedNotification failed: ' . $e->getMessage());
            }
        });

        return response()->json(['success' => true, 'message' => __('partner.fulfillment.messages.tracking_number_saved')]);
    }

    // ── FBP: inventory overview ───────────────────────────────────────────────

    public function fbpInventory(): JsonResponse
    {
        $vendor = $this->vendor();

        $inventory = WarehouseInventory::query()
            ->select('warehouse_inventories.*', 'warehouses.name as warehouse_name', 'warehouses.code as warehouse_code')
            ->join('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('vendor_listings.vendor_id', $vendor->id)
            ->where('vendor_listings.global_system_type', GlobalSystemType::MerchantFbp)
            ->with('vendorListing.productVariant.product')
            ->orderByDesc('warehouse_inventories.updated_at')
            ->get()
            ->map(function ($inv) {
                return [
                    'product' => $inv->vendorListing?->productVariant?->product?->name_en ?? '—',
                    'warehouse' => $inv->warehouse_name . ' (' . $inv->warehouse_code . ')',
                    'on_hand' => $inv->quantity_on_hand,
                    'available' => $inv->quantity_available,
                    'reserved' => $inv->quantity_reserved,
                    'inbound' => $inv->quantity_inbound,
                    'bin_location' => $inv->bin_location,
                    'reorder_point' => $inv->reorder_point,
                    'needs_reorder' => $inv->reorder_point && $inv->quantity_available <= $inv->reorder_point,
                ];
            });

        return response()->json(['success' => true, 'data' => $inventory]);
    }

    // ── Storage fees (vendor's own) ───────────────────────────────────────────

    public function storageFees(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportStorageFees($request);
        }

        $fees = $this->buildStorageFeesQuery($request)
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->map(fn($f) => [
                'month' => $f->monthLabel(),
                'units' => $f->units_stored,
                'total' => $f->totalFormatted(),
                'status' => $f->status->value,
                'status_color' => $f->statusColor(),
            ]);

        return response()->json(['success' => true, 'data' => $fees]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Storage Fees — shared query builder / export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildStorageFeesQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $vendor = $this->vendor();

        $query = FbnStorageFee::where('vendor_id', $vendor->id)
            ->with('warehouseInventory.vendorListing.productVariant.product');

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('status', $v),
            'date_from' => fn($q, $v) => $q->whereDate('month', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('month', '<=', $v),
        ]);

        return $query;
    }

    private function exportStorageFees(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fees = $this->buildStorageFeesQuery($request)->orderByDesc('month')->get();

        $headers = ['Listing', 'Month', 'Units', 'Fee/Unit', 'Total', 'Currency', 'Status'];

        $rows = $fees->map(function ($f) {
            $listing = $f->warehouseInventory?->vendorListing;
            $product = $listing?->productVariant?->product;

            return [
                $product?->name_en ?? '—',
                $f->monthLabel(),
                $f->units_stored,
                number_format($f->rate_per_unit / 100, 2),
                number_format($f->total_fee / 100, 2),
                $f->currency,
                $f->status->value,
            ];
        });

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('storage-fees', $headers, $rows),
            'csv' => $this->exportCsv('storage-fees', $headers, $rows),
            'word' => $this->exportWord('storage-fees', 'Storage Fees', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }
}
