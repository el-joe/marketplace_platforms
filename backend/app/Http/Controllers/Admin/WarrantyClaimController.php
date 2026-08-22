<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\WarrantyClaim;
use App\Models\WarrantyClaimMessage;
use App\Notifications\Customer\WarrantyClaimResolvedNotification;
use App\Notifications\Customer\WarrantyClaimStatusChanged;
use App\Services\WalletService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarrantyClaimController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_claims.view'), 403);

        if ($request->filled('export')) {
            return $this->exportClaims($request);
        }

        $stats = [
            'submitted' => WarrantyClaim::where('status', WarrantyClaim::STATUS_SUBMITTED)->count(),
            'under_review' => WarrantyClaim::where('status', WarrantyClaim::STATUS_UNDER_REVIEW)->count(),
            'approved' => WarrantyClaim::where('status', WarrantyClaim::STATUS_APPROVED)->count(),
            'resolved_30d' => WarrantyClaim::where('status', WarrantyClaim::STATUS_RESOLVED)
                ->where('resolved_at', '>=', now()->subDays(30))->count(),
        ];

        return view('admin.warranty-claims.index', compact('stats'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_claims.view'), 403);

        $query = $this->buildClaimsQuery($request);

        $columns = [
            0 => ['searchable_columns' => ['warranty_claims.claim_number'], 'orderable_column' => 'warranty_claims.claim_number'],
            1 => ['searchable_columns' => ['customers.name']],
            2 => ['searchable_columns' => ['products.name_en']],
            3 => ['searchable_columns' => ['vendors.store_name']],
            4 => ['orderable_column' => 'warranty_claims.listing_type'],
            5 => ['orderable_column' => 'warranty_claims.status'],
            6 => ['orderable_column' => 'warranty_claims.created_at'],
            7 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($w) {
            $statusBadge = $this->statusBadgeClass($w->status);
            $statusLabel = __('admin.warranty_claims_section.' . $w->status);
            $showUrl = route('admin.warranty-claims.show', $w->id);
            $isAdminListing = $w->listing_type === WarrantyClaim::LISTING_TYPE_ADMIN;

            $sellerCell = $isAdminListing
                ? '<span class="text-sm text-gray-700">' . __('admin.warranty_claims_section.platform') . '</span>'
                : '<span class="text-sm text-gray-700">' . e($w->vendor_store_name ?? '—') . '</span>';

            $listingTypeBadge = $isAdminListing
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">' . __('admin.warranty_claims_section.admin') . '</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">' . __('admin.warranty_claims_section.vendor') . '</span>';

            return [
                'DT_RowId' => 'wc-' . $w->id,
                'claim_number' => '<a href="' . $showUrl . '" class="font-mono font-medium text-primary-600 hover:underline text-xs">' . e($w->claim_number) . '</a>',
                'customer' => '<span class="text-sm text-gray-700">' . e($w->customer_name ?? '—') . '</span>',
                'product' => '<span class="text-sm text-gray-700">' . e($w->product_name ?? '—') . '</span>',
                'vendor' => $sellerCell,
                'listing_type' => $listingTypeBadge,
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($statusLabel) . '</span>',
                'created_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . \Carbon\Carbon::parse($w->created_at)->format('M d, Y H:i') . '</span>',
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">' . e(__('common.view')) . '</a>',
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query builder (shared by datatable + export)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildClaimsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        // WarrantyClaim has no direct order relation; it links to an order via
        // order_items.order_id, so we join through order_items -> orders for the
        // "Order #" export column.
        $query = WarrantyClaim::query()
            ->leftJoin('customers', 'customers.id', '=', 'warranty_claims.customer_id')
            ->leftJoin('products', 'products.id', '=', 'warranty_claims.product_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'warranty_claims.vendor_id')
            ->leftJoin('order_items', 'order_items.id', '=', 'warranty_claims.order_item_id')
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'warranty_claims.*',
                'customers.name as customer_name',
                'products.name_en as product_name',
                'vendors.store_name as vendor_store_name',
                'orders.order_number as order_number'
            );

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where('warranty_claims.claim_number', 'like', '%' . $v . '%'),
            'status' => fn($q, $v) => $q->where('warranty_claims.status', $v),
            'vendor_id' => fn($q, $v) => $q->where('warranty_claims.vendor_id', $v),
            'listing_type' => fn($q, $v) => $q->where('warranty_claims.listing_type', $v),
            'date_from' => fn($q, $v) => $q->whereDate('warranty_claims.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('warranty_claims.created_at', '<=', $v),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function exportClaims(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $claims = $this->buildClaimsQuery($request)->orderByDesc('warranty_claims.created_at')->get();

        $headers = ['Claim #', 'Order #', 'Issue Type', 'Status', 'Date'];

        $rows = $claims->map(fn($w) => [
            $w->claim_number,
            $w->order_number,
            $w->issue_type,
            $w->status,
            optional($w->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('warranty-claims', $headers, $rows),
            'csv' => $this->exportCsv('warranty-claims', $headers, $rows),
            'word' => $this->exportWord('warranty-claims', 'Warranty Claims', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(WarrantyClaim $claim): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_claims.view'), 403);

        $claim->load([
            'messages' => fn($q) => $q->orderBy('created_at', 'asc'),
            'customer:id,name,email',
            'product:id,name',
            'vendor:id,store_name,email',
            'orderItem',
            'resolvedByAdmin:id,name',
            'adminListing:id,product_variant_id,platform_sku',
        ]);

        return view('admin.warranty-claims.show', compact('claim', 'admin'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Status
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatus(Request $request, WarrantyClaim $claim): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_claims.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                WarrantyClaim::STATUS_SUBMITTED,
                WarrantyClaim::STATUS_UNDER_REVIEW,
                WarrantyClaim::STATUS_APPROVED,
                WarrantyClaim::STATUS_REJECTED,
                WarrantyClaim::STATUS_RESOLVED,
            ])],
        ]);

        $previousStatus = $claim->status;

        $claim->update(['status' => $data['status']]);

        WarrantyClaimMessage::create([
            'warranty_claim_id' => $claim->id,
            'sender_user_id' => $admin->id,
            'sender_role' => WarrantyClaimMessage::SENDER_ROLE_ADMIN,
            'message' => __('admin.warranty_claims_section.status_change_log', [
                'from' => __('admin.warranty_claims_section.' . $previousStatus),
                'to' => __('admin.warranty_claims_section.' . $data['status']),
            ]),
            'is_internal_note' => true,
            'created_at' => now(),
        ]);

        if ($claim->status !== $previousStatus) {
            $claim->loadMissing('customer');
            $claim->customer?->notify(new WarrantyClaimStatusChanged($claim, $previousStatus));
        }

        return response()->json([
            'message' => __('admin.warranty_claims_section.status_updated'),
            'status' => $claim->status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolve
    // ─────────────────────────────────────────────────────────────────────────

    public function resolve(Request $request, WarrantyClaim $claim): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_claims.manage'), 403);

        $data = $request->validate([
            'resolution' => ['required', Rule::in([
                WarrantyClaim::RESOLUTION_REPAIR,
                WarrantyClaim::RESOLUTION_REPLACE,
                WarrantyClaim::RESOLUTION_REFUND,
                WarrantyClaim::RESOLUTION_NO_ACTION,
            ])],
            'resolution_notes' => 'required|string|max:5000',
        ]);

        if ($claim->status !== WarrantyClaim::STATUS_APPROVED) {
            return response()->json([
                'message' => __('admin.warranty_claims_section.must_be_approved_first'),
            ], 422);
        }

        $previousStatus = $claim->status;

        DB::beginTransaction();
        try {
            $claim->update([
                'resolution' => $data['resolution'],
                'resolution_notes' => $data['resolution_notes'],
                'resolved_by_admin_id' => $admin->id,
                'resolved_at' => now(),
                'status' => WarrantyClaim::STATUS_RESOLVED,
            ]);

            if ($data['resolution'] === WarrantyClaim::RESOLUTION_REFUND) {
                $this->issueRefund($claim, $admin->id);
            }

            WarrantyClaimMessage::create([
                'warranty_claim_id' => $claim->id,
                'sender_user_id' => $admin->id,
                'sender_role' => WarrantyClaimMessage::SENDER_ROLE_ADMIN,
                'message' => sprintf(
                    "[%s] %s\n%s",
                    __('admin.warranty_claims_section.resolution'),
                    __('admin.warranty_claims_section.' . $data['resolution']),
                    $data['resolution_notes']
                ),
                'is_internal_note' => false,
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => __('admin.warranty_claims_section.resolve_failed')], 500);
        }

        $claim->refresh();

        $claim->loadMissing('customer');
        $claim->customer?->notify(new WarrantyClaimResolvedNotification($claim));

        if ($claim->status !== $previousStatus) {
            $claim->customer?->notify(new WarrantyClaimStatusChanged($claim, $previousStatus));
        }

        return response()->json([
            'message' => __('admin.warranty_claims_section.resolved_message'),
            'status' => $claim->status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Add message
    // ─────────────────────────────────────────────────────────────────────────

    public function addMessage(Request $request, WarrantyClaim $claim): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_claims.manage'), 403);

        $data = $request->validate([
            'message' => 'required|string|max:10000',
            'is_internal_note' => 'boolean',
        ]);

        $isInternal = (bool) ($data['is_internal_note'] ?? false);

        $message = WarrantyClaimMessage::create([
            'warranty_claim_id' => $claim->id,
            'sender_user_id' => $admin->id,
            'sender_role' => WarrantyClaimMessage::SENDER_ROLE_ADMIN,
            'message' => $data['message'],
            'is_internal_note' => $isInternal,
            'created_at' => now(),
        ]);

        $previousStatus = $claim->status;
        if (!$isInternal && $claim->status === WarrantyClaim::STATUS_SUBMITTED) {
            $claim->update(['status' => WarrantyClaim::STATUS_UNDER_REVIEW]);
        }

        if ($claim->status !== $previousStatus) {
            $claim->loadMissing('customer');
            $claim->customer?->notify(new WarrantyClaimStatusChanged($claim, $previousStatus));
        }

        return response()->json([
            'message' => $isInternal
                ? __('admin.warranty_claims_section.internal_note_saved')
                : __('admin.warranty_claims_section.reply_sent'),
            'status' => $claim->status,
            'sender_name' => $admin->name,
            'created_at' => $message->created_at->format('M d, Y H:i'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function issueRefund(WarrantyClaim $claim, string $adminId): void
    {
        $claim->loadMissing('orderItem.order', 'orderItem.subOrder');
        $orderItem = $claim->orderItem;

        if (!$orderItem?->order) {
            return;
        }

        $originalTransaction = PaymentTransaction::where('order_id', $orderItem->order_id)
            ->whereIn('type', ['sale', 'capture'])
            ->where('status', 'succeeded')
            ->latest('created_at')
            ->first();

        if (!$originalTransaction) {
            return;
        }

        // For admin_listing claims the refund is charged back to the platform, not a vendor.
        $vendorChargedBack = $claim->listing_type === WarrantyClaim::LISTING_TYPE_VENDOR;

        $refund = Refund::create([
            'order_id' => $orderItem->order_id,
            'sub_order_id' => $orderItem->sub_order_id,
            'original_transaction_id' => $originalTransaction->id,
            'amount' => $orderItem->total ?? $orderItem->price ?? 0,
            'currency' => $orderItem->order->currency ?? 'USD',
            'reason' => 'not_as_described',
            'reason_notes' => 'Warranty claim #' . $claim->claim_number,
            'refund_type' => 'partial',
            'initiated_by_customer_id' => $claim->customer_id,
            'approved_by_admin_id' => $adminId,
            'vendor_charged_back' => $vendorChargedBack,
            'status' => 'approved',
        ]);

        $walletService = app(WalletService::class);
        $wallet = $walletService->getOrCreateWallet('customer', $claim->customer_id, $refund->currency);

        $walletService->credit(
            $wallet,
            (int) $refund->amount,
            'warranty_claim',
            $claim->id,
            "Warranty claim refund #{$claim->claim_number}",
            $adminId
        );
    }

    protected function statusBadgeClass(string $status): string
    {
        return match ($status) {
            WarrantyClaim::STATUS_SUBMITTED => 'bg-blue-100 text-blue-700',
            WarrantyClaim::STATUS_UNDER_REVIEW => 'bg-yellow-100 text-yellow-700',
            WarrantyClaim::STATUS_APPROVED => 'bg-green-100 text-green-700',
            WarrantyClaim::STATUS_REJECTED => 'bg-red-100 text-red-700',
            WarrantyClaim::STATUS_RESOLVED => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
