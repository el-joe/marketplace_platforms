<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FbnInboundRequestStatus;
use App\Enums\FbnStorageFeeStatus;
use App\Enums\MarketplaceShippingRuleCommissionType;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateFbnStorageFeesJob;
use App\Models\FbnInboundRequest;
use App\Models\FbnStorageFee;
use App\Models\MarketplaceShippingRule;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FbnController extends Controller
{
    use HasDataTable;

    // ══════════════════════════════════════════════════════════════════════════
    // FBN INBOUND REQUESTS
    // ══════════════════════════════════════════════════════════════════════════

    public function inboundIndex(): View
    {
        $stats = [
            'total' => FbnInboundRequest::count(),
            'draft' => FbnInboundRequest::where('status', FbnInboundRequestStatus::Draft)->count(),
            'submitted' => FbnInboundRequest::where('status', FbnInboundRequestStatus::Submitted)->count(),
            'approved' => FbnInboundRequest::where('status', FbnInboundRequestStatus::Approved)->count(),
            'shipped' => FbnInboundRequest::where('status', FbnInboundRequestStatus::Shipped)->count(),
            'received' => FbnInboundRequest::where('status', FbnInboundRequestStatus::Received)->count(),
            'rejected' => FbnInboundRequest::where('status', FbnInboundRequestStatus::Rejected)->count(),
        ];

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.fbn.inbound.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'FBN / Fulfillment'],
                ['label' => 'Inbound Requests'],
            ],
            'stats' => $stats,
            'warehouses' => $warehouses,
        ]);
    }

    public function inboundDatatable(Request $request): JsonResponse
    {
        $query = FbnInboundRequest::query()
            ->select([
                'fbn_inbound_requests.*',
                'vendors.store_name as vendor_name',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
            ])
            ->join('vendors', 'vendors.id', '=', 'fbn_inbound_requests.vendor_id')
            ->join('warehouses', 'warehouses.id', '=', 'fbn_inbound_requests.warehouse_id')
            ->with(['vendorListing.productVariant.product']);

        if ($request->filled('status')) {
            $query->where('fbn_inbound_requests.status', $request->status);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('fbn_inbound_requests.warehouse_id', $request->warehouse_id);
        }

        return $this->dataTableResponse($request, $query, [
            'searchable_columns' => [
                'fbn_inbound_requests.request_number',
                'vendors.store_name',
                'fbn_inbound_requests.tracking_number',
            ],
            'orderable_column' => 'fbn_inbound_requests.created_at',
        ], function ($row) {
            $product = $row->vendorListing?->productVariant?->product;
            $statusBadge = '<span class="badge badge-' . $row->statusColor() . '">' . $row->statusLabel() . '</span>';

            $actions = '<div class="flex gap-1 flex-wrap">';
            if ($row->canBeApproved()) {
                $actions .= '<button class="btn btn-xs btn-success btn-approve-inbound" data-id="' . $row->id . '">Approve</button>';
            }
            if ($row->canBeRejected()) {
                $actions .= '<button class="btn btn-xs btn-danger btn-reject-inbound" data-id="' . $row->id . '" data-number="' . $row->request_number . '">Reject</button>';
            }
            if ($row->canMarkShipped()) {
                $actions .= '<button class="btn btn-xs btn-primary btn-update-tracking" data-id="' . $row->id . '">Tracking</button>';
            }
            if ($row->canMarkReceived()) {
                $actions .= '<button class="btn btn-xs btn-success btn-mark-received" data-id="' . $row->id . '" data-max="' . $row->quantity_requested . '">Receive</button>';
            }
            $actions .= '</div>';

            return [
                'request_number' => '<span class="font-mono text-xs">' . $row->request_number . '</span>',
                'vendor' => e($row->vendor_name),
                'product' => $product ? '<div class="text-xs">' . e($product->name_en) . '</div>' : '—',
                'warehouse' => '<span class="text-xs">' . e($row->warehouse_name) . ' <span class="badge badge-secondary">' . $row->warehouse_code . '</span></span>',
                'qty' => $row->quantity_requested . ' / <span class="text-green-600 font-semibold">' . $row->quantity_received . '</span>',
                'status' => $statusBadge,
                'expected' => $row->expected_arrival?->format('d M Y') ?? '—',
                'tracking' => $row->tracking_number ? '<span class="font-mono text-xs">' . e($row->tracking_number) . '</span>' : '—',
                'created_at' => $row->created_at->format('d M Y'),
                'actions' => $actions,
            ];
        });
    }

    public function approveInbound(Request $request, FbnInboundRequest $inboundRequest): JsonResponse
    {
        if (!$inboundRequest->canBeApproved()) {
            return response()->json(['success' => false, 'message' => 'Request cannot be approved in its current state.'], 422);
        }

        $data = $request->validate([
            'expected_arrival' => 'nullable|date|after_or_equal:today',
        ]);

        $inboundRequest->update([
            'status' => FbnInboundRequestStatus::Approved,
            'admin_approved_by' => Auth::guard('admin')->id(),
            'approved_at' => now(),
            'expected_arrival' => $data['expected_arrival'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Inbound request approved.']);
    }

    public function rejectInbound(Request $request, FbnInboundRequest $inboundRequest): JsonResponse
    {
        if (!$inboundRequest->canBeRejected()) {
            return response()->json(['success' => false, 'message' => 'Request cannot be rejected in its current state.'], 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $inboundRequest->update([
            'status' => FbnInboundRequestStatus::Rejected,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return response()->json(['success' => true, 'message' => 'Inbound request rejected.']);
    }

    public function updateTracking(Request $request, FbnInboundRequest $inboundRequest): JsonResponse
    {
        if (!$inboundRequest->canMarkShipped()) {
            return response()->json(['success' => false, 'message' => 'Request must be in approved status to update tracking.'], 422);
        }

        $data = $request->validate([
            'tracking_number' => 'required|string|max:100',
            'expected_arrival' => 'nullable|date',
        ]);

        $inboundRequest->update([
            'status' => FbnInboundRequestStatus::Shipped,
            'tracking_number' => $data['tracking_number'],
            'expected_arrival' => $data['expected_arrival'] ?? $inboundRequest->expected_arrival,
        ]);

        return response()->json(['success' => true, 'message' => 'Tracking updated and status set to Shipped.']);
    }

    public function receiveInbound(Request $request, FbnInboundRequest $inboundRequest): JsonResponse
    {
        if (!$inboundRequest->canMarkReceived()) {
            return response()->json(['success' => false, 'message' => 'Request must be in shipped status to mark received.'], 422);
        }

        $data = $request->validate([
            'quantity_received' => 'required|integer|min:1|max:' . $inboundRequest->quantity_requested,
        ]);

        DB::transaction(function () use ($inboundRequest, $data) {
            $inboundRequest->update([
                'status' => FbnInboundRequestStatus::Received,
                'quantity_received' => $data['quantity_received'],
            ]);

            // Update warehouse inventory quantity_inbound → quantity_on_hand
            $inventory = \App\Models\WarehouseInventory::where('vendor_listing_id', $inboundRequest->vendor_listing_id)
                ->where('warehouse_id', $inboundRequest->warehouse_id)
                ->first();

            if ($inventory) {
                $inventory->increment('quantity_on_hand', $data['quantity_received']);
                $inventory->decrement('quantity_inbound', min($data['quantity_received'], $inventory->quantity_inbound));
            }
        });

        return response()->json(['success' => true, 'message' => 'Items received and inventory updated.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FBN STORAGE FEES
    // ══════════════════════════════════════════════════════════════════════════

    public function storageFeesIndex(): View
    {
        $stats = [
            'total' => FbnStorageFee::count(),
            'pending' => FbnStorageFee::where('status', FbnStorageFeeStatus::Pending)->count(),
            'invoiced' => FbnStorageFee::where('status', FbnStorageFeeStatus::Invoiced)->count(),
            'paid' => FbnStorageFee::where('status', FbnStorageFeeStatus::Paid)->count(),
            'pending_revenue' => FbnStorageFee::where('status', FbnStorageFeeStatus::Pending)->sum('total_fee'),
            'paid_revenue' => FbnStorageFee::where('status', FbnStorageFeeStatus::Paid)->sum('total_fee'),
            'currency' => FbnStorageFee::query()->value('currency') ?? '',
        ];

        // Distinct months for filter
        $months = FbnStorageFee::selectRaw('DATE_FORMAT(month, "%Y-%m-01") as month_key, DATE_FORMAT(month, "%M %Y") as month_label')
            ->groupBy('month_key', 'month_label')
            ->orderByDesc('month_key')
            ->get();

        return view('admin.fbn.storage-fees.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'FBN / Fulfillment'],
                ['label' => 'Storage Fees'],
            ],
            'stats' => $stats,
            'months' => $months,
        ]);
    }

    public function storageFeesDatatable(Request $request): JsonResponse
    {
        $query = FbnStorageFee::query()
            ->select([
                'fbn_storage_fees.*',
                'vendors.store_name as vendor_name',
            ])
            ->join('vendors', 'vendors.id', '=', 'fbn_storage_fees.vendor_id');

        if ($request->filled('status')) {
            $query->where('fbn_storage_fees.status', $request->status);
        }
        if ($request->filled('month')) {
            $query->whereDate('fbn_storage_fees.month', $request->month);
        }

        return $this->dataTableResponse($request, $query, [
            'searchable_columns' => ['vendors.store_name'],
            'orderable_column' => 'fbn_storage_fees.month',
        ], function ($row) {
            $actions = '';
            if ($row->status === FbnStorageFeeStatus::Pending) {
                $actions = '<button class="btn btn-xs btn-primary btn-mark-invoiced" data-id="' . $row->id . '">Mark Invoiced</button>';
            } elseif ($row->status === FbnStorageFeeStatus::Invoiced) {
                $actions = '<button class="btn btn-xs btn-success btn-mark-fee-paid" data-id="' . $row->id . '">Mark Paid</button>';
            }

            return [
                'vendor' => e($row->vendor_name),
                'month' => $row->monthLabel(),
                'units_stored' => number_format($row->units_stored),
                'rate' => number_format($row->rate_per_unit / 100, 2) . ' ' . $row->currency,
                'total_fee' => '<span class="font-semibold">' . $row->totalFormatted() . '</span>',
                'status' => '<span class="badge badge-' . $row->statusColor() . '">' . $row->status->label() . '</span>',
                'actions' => $actions,
            ];
        });
    }

    public function updateStorageFeeStatus(Request $request, FbnStorageFee $fee): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FbnStorageFeeStatus::class)->only([
                FbnStorageFeeStatus::Invoiced,
                FbnStorageFeeStatus::Paid,
            ])],
        ]);

        $newStatus = FbnStorageFeeStatus::from($data['status']);

        if ($newStatus === FbnStorageFeeStatus::Invoiced && $fee->status !== FbnStorageFeeStatus::Pending) {
            return response()->json(['success' => false, 'message' => 'Only pending fees can be marked invoiced.'], 422);
        }
        if ($newStatus === FbnStorageFeeStatus::Paid && $fee->status !== FbnStorageFeeStatus::Invoiced) {
            return response()->json(['success' => false, 'message' => 'Only invoiced fees can be marked paid.'], 422);
        }

        $fee->update(['status' => $newStatus]);

        return response()->json(['success' => true, 'message' => 'Storage fee status updated.']);
    }

    public function generateMonthlyFees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        GenerateFbnStorageFeesJob::dispatch($data['month']);

        return response()->json([
            'success' => true,
            'message' => 'Storage fee generation job queued for ' . $data['month'] . '.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MARKETPLACE SHIPPING RULES
    // ══════════════════════════════════════════════════════════════════════════

    public function marketplaceIndex(): View
    {
        $stats = [
            'total' => MarketplaceShippingRule::count(),
            'special_vehicle' => MarketplaceShippingRule::where('requires_special_vehicle', 1)->count(),
            'refrigerated' => MarketplaceShippingRule::where('requires_refrigeration', 1)->count(),
            'fixed_commission' => MarketplaceShippingRule::where('commission_type', MarketplaceShippingRuleCommissionType::Fixed)->count(),
        ];

        return view('admin.fbn.marketplace-rules.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'FBN / Fulfillment'],
                ['label' => 'Marketplace Rules'],
            ],
            'stats' => $stats,
        ]);
    }

    public function marketplaceDatatable(Request $request): JsonResponse
    {
        $query = MarketplaceShippingRule::query()
            ->select([
                'marketplace_shipping_rules.*',
                'vendors.store_name as vendor_name',
            ])
            ->join('vendor_listings', 'vendor_listings.id', '=', 'marketplace_shipping_rules.vendor_listing_id')
            ->join('vendors', 'vendors.id', '=', 'vendor_listings.vendor_id')
            ->with('vendorListing.productVariant.product');

        if ($request->filled('commission_type')) {
            $query->where('marketplace_shipping_rules.commission_type', $request->commission_type);
        }

        return $this->dataTableResponse($request, $query, [
            'searchable_columns' => ['vendors.store_name'],
            'orderable_column' => 'marketplace_shipping_rules.created_at',
        ], function ($row) {
            $product = $row->vendorListing?->productVariant?->product;
            $flags = '';
            if ($row->requires_special_vehicle) {
                $flags .= '<span class="badge badge-warning text-xs me-1">Special Vehicle</span>';
            }
            if ($row->requires_refrigeration) {
                $flags .= '<span class="badge badge-primary text-xs me-1">Refrigerated</span>';
            }

            $actions = '<div class="flex gap-1">'
                . '<button class="btn btn-xs btn-ghost btn-edit-rule" data-rule=\'' . htmlspecialchars(json_encode($row), ENT_QUOTES) . '\'>Edit</button>'
                . '<button class="btn btn-xs btn-danger btn-delete-rule" data-id="' . $row->id . '">Del</button>'
                . '</div>';

            return [
                'listing_id' => '<span class="font-mono text-xs">' . substr($row->vendor_listing_id, 0, 8) . '…</span>',
                'vendor' => e($row->vendor_name),
                'product' => $product ? e($product->name_en) : '—',
                'flags' => $flags ?: '<span class="text-gray-300">—</span>',
                'weight' => $row->max_weight_kg ? $row->max_weight_kg . ' kg' : '—',
                'commission' => $row->commissionLabel(),
                'extra_fee' => $row->extra_delivery_fee > 0 ? $row->extraFeeFormatted() : '—',
                'actions' => $actions,
            ];
        });
    }

    public function storeMarketplaceRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_listing_id' => 'required|exists:vendor_listings,id',
            'requires_special_vehicle' => 'boolean',
            'requires_refrigeration' => 'boolean',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'max_dimensions_cm' => 'nullable|string|max:50',
            'special_handling_notes' => 'nullable|string',
            'commission_type' => ['required', Rule::enum(MarketplaceShippingRuleCommissionType::class)],
            'commission_value' => 'required|numeric|min:0',
            'extra_delivery_fee' => 'integer|min:0',
        ]);

        if (MarketplaceShippingRule::where('vendor_listing_id', $data['vendor_listing_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'A rule already exists for this listing. Edit the existing one.'], 422);
        }

        $rule = MarketplaceShippingRule::create($data);

        return response()->json(['success' => true, 'message' => 'Marketplace rule created.', 'id' => $rule->id]);
    }

    public function updateMarketplaceRule(Request $request, MarketplaceShippingRule $rule): JsonResponse
    {
        $data = $request->validate([
            'requires_special_vehicle' => 'boolean',
            'requires_refrigeration' => 'boolean',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'max_dimensions_cm' => 'nullable|string|max:50',
            'special_handling_notes' => 'nullable|string',
            'commission_type' => ['required', Rule::enum(MarketplaceShippingRuleCommissionType::class)],
            'commission_value' => 'required|numeric|min:0',
            'extra_delivery_fee' => 'integer|min:0',
        ]);

        $rule->update($data);

        return response()->json(['success' => true, 'message' => 'Marketplace rule updated.']);
    }

    public function destroyMarketplaceRule(MarketplaceShippingRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['success' => true, 'message' => 'Marketplace rule deleted.']);
    }
}
