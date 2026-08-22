<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnRequestLiability;
use App\Enums\ReturnRequestStatus;
use App\Enums\ReturnRequestType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ReturnRequest;
use App\Models\Vendor;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Customer\ReturnApprovedNotification;
use App\Notifications\Customer\ReturnRejectedNotification;
use App\Notifications\Customer\ReturnStatusChangedNotification;
use App\Notifications\Vendor\ReturnApprovedForVendorNotification;
use App\Notifications\Vendor\ReturnInspectedNotification;
use App\Services\OrderInterventionService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReturnController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(private readonly OrderInterventionService $interventionService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.view'), 403);

        if ($request->filled('export')) {
            return $this->exportReturns($request);
        }

        $stats = [
            'pending' => ReturnRequest::where('status', ReturnRequestStatus::Requested->value)->count(),
            'pickup_scheduled' => ReturnRequest::where('status', ReturnRequestStatus::AwaitingPickup->value)->count(),
            'in_transit' => ReturnRequest::where('status', ReturnRequestStatus::InTransit->value)->count(),
            'received' => ReturnRequest::where('status', ReturnRequestStatus::Received->value)->count(),
            'inspected' => ReturnRequest::where('status', ReturnRequestStatus::Inspecting->value)->count(),
            'refunded' => ReturnRequest::where('status', ReturnRequestStatus::Completed->value)->count(),
        ];

        $vendors = Vendor::orderBy('store_name')->get(['id', 'store_name']);

        return view('admin.returns.index', compact('stats', 'vendors'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.view'), 403);

        $query = $this->buildReturnsQuery($request);

        $columns = [
            0 => ['searchable_columns' => ['return_requests.return_number'], 'orderable_column' => 'return_requests.return_number'],
            1 => ['searchable_columns' => ['orders.order_number']],
            2 => ['searchable_columns' => ['customers.name']],
            3 => ['searchable_columns' => ['vendors.store_name']],
            4 => ['orderable_column' => 'return_requests.reason'],
            5 => [],
            6 => ['orderable_column' => 'return_requests.status'],
            7 => ['orderable_column' => 'return_requests.created_at'],
            8 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($r) {
            $statusBadge = $this->statusBadgeClass($r->status->value);
            $statusLabel = __('admin.returns_section.' . $r->status->value);
            $reasonLabel = __('admin.returns_section.reason_' . $r->reason->value);

            $showUrl = route('admin.returns.show', $r->id);

            $orderCell = $r->order_number
                ? '<a href="' . route('admin.orders.show', $r->order_id) . '" class="text-xs font-mono text-primary-600 hover:underline">' . e($r->order_number) . '</a>'
                : '<span class="text-xs text-gray-300">—</span>';

            return [
                'DT_RowId' => 'ret-' . $r->id,
                'return_number' => '<a href="' . $showUrl . '" class="font-mono font-medium text-primary-600 hover:underline text-xs">' . e($r->return_number) . '</a>',
                'order' => $orderCell,
                'customer' => '<span class="text-sm text-gray-700">' . e($r->customer_name ?? '—') . '</span>',
                'vendor' => '<span class="text-sm text-gray-700">' . e($r->vendor_store_name ?? '—') . '</span>',
                'reason' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">' . e($reasonLabel) . '</span>',
                'items_count' => '<span class="text-sm text-gray-700">' . (int) $r->items_count . '</span>',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($statusLabel) . '</span>',
                'created_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . \Carbon\Carbon::parse($r->created_at)->format('M d, Y H:i') . '</span>',
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">' . e(__('common.view')) . '</a>',
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query builder (shared by datatable + export)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildReturnsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = ReturnRequest::query()
            ->leftJoin('orders', 'orders.id', '=', 'return_requests.order_id')
            ->leftJoin('customers', 'customers.id', '=', 'return_requests.customer_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'return_requests.vendor_id')
            ->withCount('items')
            ->select(
                'return_requests.*',
                'orders.order_number as order_number',
                'customers.name as customer_name',
                'vendors.store_name as vendor_store_name'
            );

        return $this->applyFilters($query, $request, [
            'search' => fn ($q, $v) => $q->where('return_requests.return_number', 'like', '%' . $v . '%'),
            'status' => fn ($q, $v) => $q->where('return_requests.status', $v),
            'vendor_id' => fn ($q, $v) => $q->where('return_requests.vendor_id', $v),
            'date_from' => fn ($q, $v) => $q->whereDate('return_requests.created_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('return_requests.created_at', '<=', $v),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function exportReturns(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $returns = $this->buildReturnsQuery($request)->orderByDesc('return_requests.created_at')->get();

        $headers = ['Return #', 'Order #', 'Reason', 'Status', 'Date'];

        $rows = $returns->map(fn($r) => [
            $r->return_number,
            $r->order_number,
            $r->reason?->value,
            $r->status?->value,
            optional($r->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('returns', $headers, $rows),
            'csv' => $this->exportCsv('returns', $headers, $rows),
            'word' => $this->exportWord('returns', 'Returns', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(ReturnRequest $returnRequest): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.view'), 403);

        $returnRequest->load([
            'items.orderItem',
            'messages' => fn ($q) => $q->orderBy('created_at', 'asc'),
            'customer:id,name,email',
            'vendor:id,store_name,email',
            'order:id,order_number,status,payment_status,total,currency',
            'subOrder:id,sub_order_number,status,subtotal',
            'pickupAddress',
            'reviewedByAdmin:id,name',
            'refund',
        ]);

        return view('admin.returns.show', compact('returnRequest', 'admin'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approve
    // ─────────────────────────────────────────────────────────────────────────

    public function approve(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.manage'), 403);

        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            return response()->json(['message' => __('admin.returns_section.approve_invalid_state')], 422);
        }

        DB::transaction(function () use ($returnRequest, $admin) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::Approved->value,
                'reviewed_by_admin_id' => $admin->id,
            ]);
        });

        $returnRequest->refresh()->loadMissing(['customer', 'vendor.vendorAdmins']);
        $returnRequest->customer?->notify(new ReturnApprovedNotification($returnRequest));

        if ($returnRequest->vendor?->vendorAdmins->isNotEmpty()) {
            Notification::send($returnRequest->vendor->vendorAdmins, new ReturnApprovedForVendorNotification($returnRequest));
        }

        return response()->json([
            'message' => __('admin.returns_section.approved_message'),
            'status' => $returnRequest->status->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schedule Pickup
    // ─────────────────────────────────────────────────────────────────────────

    public function schedulePickup(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.manage'), 403);

        if ($returnRequest->status !== ReturnRequestStatus::Approved) {
            return response()->json(['message' => __('admin.returns_section.schedule_pickup_invalid_state')], 422);
        }

        $data = $request->validate([
            'scheduled_date' => 'nullable|date',
        ]);

        $returnRequest->update([
            'status' => ReturnRequestStatus::AwaitingPickup->value,
            'pickup_scheduled_at' => $data['scheduled_date'] ?? null,
        ]);

        $returnRequest->loadMissing('customer');
        $returnRequest->customer?->notify(new ReturnStatusChangedNotification($returnRequest));

        return response()->json([
            'message' => __('admin.returns_section.pickup_scheduled_message'),
            'status' => $returnRequest->status->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mark Received
    // ─────────────────────────────────────────────────────────────────────────

    public function markReceived(ReturnRequest $returnRequest): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.manage'), 403);

        if (!in_array($returnRequest->status, [ReturnRequestStatus::AwaitingPickup, ReturnRequestStatus::InTransit], true)) {
            return response()->json(['message' => __('admin.returns_section.mark_received_invalid_state')], 422);
        }

        $returnRequest->update([
            'status' => ReturnRequestStatus::Received->value,
            'received_at_warehouse_at' => now(),
        ]);

        return response()->json([
            'message' => __('admin.returns_section.received_message'),
            'status' => $returnRequest->status->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inspect
    // ─────────────────────────────────────────────────────────────────────────

    public function inspect(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.manage'), 403);

        if ($returnRequest->status !== ReturnRequestStatus::Received) {
            return response()->json(['message' => __('admin.returns_section.inspect_invalid_state')], 422);
        }

        $data = $request->validate([
            'inspection_outcome' => 'required|in:good,damaged_by_customer,damaged_in_transit',
            'inspection_notes' => 'nullable|string|max:5000',
        ]);

        try {
            DB::transaction(function () use ($returnRequest, $data, $admin) {
                match ($data['inspection_outcome']) {
                    'good' => $this->handleGoodInspection($returnRequest, $data, $admin),
                    'damaged_by_customer' => $this->handleDamagedByCustomerInspection($returnRequest, $data),
                    'damaged_in_transit' => $this->handleDamagedInTransitInspection($returnRequest, $data, $admin),
                };
            });
        } catch (\Throwable $e) {
            Log::error('Return inspection failed', ['return_request_id' => $returnRequest->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => __('admin.returns_section.inspect_failed')], 500);
        }

        $returnRequest->refresh()->loadMissing('vendor.vendorAdmins');
        if ($returnRequest->vendor?->vendorAdmins->isNotEmpty()) {
            Notification::send($returnRequest->vendor->vendorAdmins, new ReturnInspectedNotification($returnRequest));
        }

        return response()->json([
            'message' => __('admin.returns_section.inspected_message'),
            'status' => $returnRequest->status->value,
        ]);
    }

    private function handleGoodInspection(ReturnRequest $returnRequest, array $data, $admin): void
    {
        $returnRequest->update([
            'status' => ReturnRequestStatus::Completed->value,
            'inspection_result' => 'good',
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'liability' => ReturnRequestLiability::Platform->value,
        ]);

        $this->processReturnRefund($returnRequest, $admin);
        $this->restockInventory($returnRequest, $admin);
    }

    private function handleDamagedByCustomerInspection(ReturnRequest $returnRequest, array $data): void
    {
        $returnRequest->update([
            'status' => ReturnRequestStatus::Inspecting->value,
            'inspection_result' => 'damaged',
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'liability' => ReturnRequestLiability::Customer->value,
        ]);
    }

    private function handleDamagedInTransitInspection(ReturnRequest $returnRequest, array $data, $admin): void
    {
        $returnRequest->update([
            'status' => ReturnRequestStatus::Completed->value,
            'inspection_result' => 'damaged',
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'liability' => ReturnRequestLiability::Carrier->value,
        ]);

        $this->processReturnRefund($returnRequest, $admin);

        $shipment = $returnRequest->sub_order_id
            ? \App\Models\Shipment::where('sub_order_id', $returnRequest->sub_order_id)->first()
            : null;

        if ($shipment) {
            \App\Models\CarrierClaim::create([
                'claim_number' => 'CLM-' . strtoupper(uniqid()),
                'shipment_id' => $shipment->id,
                'claim_type' => 'damaged',
                'description' => 'Item found damaged in transit during return inspection for ' . $returnRequest->return_number,
                'claimed_amount' => $returnRequest->refund_amount_cents ?? 0,
                'status' => 'submitted',
            ]);
        }
    }

    private function processReturnRefund(ReturnRequest $returnRequest, $admin): void
    {
        $order = $returnRequest->order()->first();
        if (!$order) {
            return;
        }

        $refund = $this->interventionService->processRefund(
            $order,
            'full',
            null,
            'wrong_item',
            'Return request ' . $returnRequest->return_number . ' inspected — refund issued.',
            $returnRequest->sub_order_id,
            false,
            (string) $admin->id
        );

        $returnRequest->update(['refund_id' => $refund->id]);

        if ($returnRequest->return_type === ReturnRequestType::StoreCredit && $returnRequest->status === ReturnRequestStatus::Completed) {
            $customer = $returnRequest->customer()->first();

            $completedRefund = \App\Models\Refund::where('order_id', $returnRequest->order_id)
                ->where('status', 'completed')
                ->latest()
                ->first();

            $creditAmount = $completedRefund?->net_refund ?? 0;

            if ($customer && $order && $creditAmount > 0) {
                app(\App\Services\Customer\CheckoutWalletService::class)->refundToWallet($customer, $order, $creditAmount);
            }
        }
    }

    private function restockInventory(ReturnRequest $returnRequest, $admin): void
    {
        foreach ($returnRequest->items as $item) {
            $vendorListingId = $item->orderItem?->vendor_listing_id;
            if (!$vendorListingId) {
                continue;
            }

            $inventory = WarehouseInventory::where('vendor_listing_id', $vendorListingId)->first();
            if (!$inventory) {
                continue;
            }

            $inventory->increment('quantity_on_hand', $item->quantity);

            InventoryMovement::create([
                'warehouse_inventory_id' => $inventory->id,
                'movement_type' => 'return',
                'quantity_delta' => $item->quantity,
                'quantity_after' => $inventory->fresh()->quantity_on_hand,
                'reference_type' => 'adjustment',
                'reference_id' => $returnRequest->id,
                'reason' => 'Return request ' . $returnRequest->return_number . ' — good condition restock',
                'created_by_user_id' => $admin->id,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reject
    // ─────────────────────────────────────────────────────────────────────────

    public function reject(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('returns.manage'), 403);

        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            return response()->json(['message' => __('admin.returns_section.reject_invalid_state')], 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $returnRequest->update([
            'status' => ReturnRequestStatus::Rejected->value,
            'rejection_reason' => $data['rejection_reason'],
            'reviewed_by_admin_id' => $admin->id,
        ]);

        $returnRequest->loadMissing('customer');
        $returnRequest->customer?->notify(new ReturnRejectedNotification($returnRequest));

        return response()->json([
            'message' => __('admin.returns_section.rejected_message'),
            'status' => $returnRequest->status->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'requested' => 'bg-amber-100 text-amber-700',
            'approved' => 'bg-blue-100 text-blue-700',
            'awaiting_pickup' => 'bg-blue-100 text-blue-700',
            'in_transit' => 'bg-indigo-100 text-indigo-700',
            'received' => 'bg-purple-100 text-purple-700',
            'inspecting' => 'bg-purple-100 text-purple-700',
            'completed' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700 border border-red-200',
            'cancelled' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
