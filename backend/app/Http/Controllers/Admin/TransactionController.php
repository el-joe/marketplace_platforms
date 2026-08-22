<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RefundProcessingJob;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─── Transactions ─────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.view'), 403);

        if ($request->filled('export')) {
            return $this->exportTransactions($request);
        }

        $today = today();

        $stats = [
            'volume_today' => PaymentTransaction::where('status', 'succeeded')
                ->whereDate('processed_at', $today)
                ->sum('amount'),
            'succeeded_today' => PaymentTransaction::where('status', 'succeeded')
                ->whereDate('processed_at', $today)
                ->count(),
            'failed_today' => PaymentTransaction::where('status', 'failed')
                ->whereDate('created_at', $today)
                ->count(),
            'pending_refunds' => Refund::where('status', 'pending')->count(),
        ];

        $gateways = PaymentTransaction::distinct()->orderBy('gateway')->pluck('gateway');

        return view('admin.transactions.index', compact('stats', 'gateways'));
    }

    /**
     * Base query for the transactions listing, shared by datatable() and export.
     *
     * Note: payment_transactions has no `payment_method` or `reference_number` column.
     * payment_transactions.gateway contains the gateway code (thawani, cod, bank_transfer, etc.)
     * and serves as the payment method identifier. gateway_transaction_id / idempotency_key
     * are the reference identifiers.
     */
    private function buildTransactionsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = PaymentTransaction::query()
            ->leftJoin('orders', 'orders.id', '=', 'payment_transactions.order_id')
            ->leftJoin('customers', 'customers.id', '=', 'payment_transactions.customer_id')
            ->select(
                'payment_transactions.*',
                'orders.order_number',
                'customers.name as customer_name',
                'payment_transactions.gateway as payment_method_type'
            );

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('payment_transactions.gateway_transaction_id', 'like', "%{$v}%")
                    ->orWhere('payment_transactions.idempotency_key', 'like', "%{$v}%");
            }),
            'type' => fn($q, $v) => $q->where('payment_transactions.type', $v),
            'payment_method' => fn($q, $v) => $q->where('payment_transactions.gateway', $v),
            'status' => fn($q, $v) => $q->where('payment_transactions.status', $v),
            'gateway' => fn($q, $v) => $q->where('payment_transactions.gateway', $v),
            'date_from' => fn($q, $v) => $q->whereDate('payment_transactions.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('payment_transactions.created_at', '<=', $v),
            'amount_min' => fn($q, $v) => $q->where('payment_transactions.amount', '>=', (int) round($v * 100)),
            'amount_max' => fn($q, $v) => $q->where('payment_transactions.amount', '<=', (int) round($v * 100)),
        ]);
    }

    /**
     * Excel export of transactions matching current filters.
     * Amounts are exported per-row with their own currency — never summed across currencies.
     */
    private function exportTransactions(Request $request): StreamedResponse
    {
        $transactions = $this->buildTransactionsQuery($request)
            ->orderByDesc('payment_transactions.created_at')
            ->get();

        $headers = ['Reference', 'Method', 'Amount', 'Currency', 'Status', 'Gateway', 'Date'];

        $rows = $transactions->map(fn($tx) => [
            $tx->gateway_transaction_id,
            $tx->payment_method_type,
            number_format($tx->amount / 100, 2),
            strtoupper($tx->currency),
            $tx->status?->value,
            $tx->gateway,
            optional($tx->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('transactions', $headers, $rows),
            'csv' => $this->exportCsv('transactions', $headers, $rows),
            'word' => $this->exportWord('transactions', 'Transactions', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.view'), 403);

        $query = $this->buildTransactionsQuery($request);

        $columns = [
            0 => ['searchable_columns' => ['payment_transactions.gateway_transaction_id'], 'orderable_column' => 'payment_transactions.gateway_transaction_id'],
            1 => ['searchable_columns' => ['orders.order_number'], 'orderable_column' => 'orders.order_number'],
            2 => ['searchable_columns' => ['customers.name'], 'orderable_column' => 'customers.name'],
            3 => ['orderable_column' => 'payment_transactions.type'],
            4 => ['orderable_column' => 'payment_transactions.gateway'],
            5 => ['orderable_column' => 'payment_transactions.amount'],
            6 => ['orderable_column' => 'payment_transactions.status'],
            7 => [],
            8 => ['orderable_column' => 'payment_transactions.processed_at'],
            9 => ['orderable_column' => 'payment_transactions.created_at'],
            10 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($tx) {
            $typeBadge = match ($tx->type->value) {
                'authorization' => 'bg-blue-100 text-blue-700',
                'capture' => 'bg-indigo-100 text-indigo-700',
                'sale' => 'bg-green-100 text-green-700',
                'refund' => 'bg-orange-100 text-orange-700',
                'void' => 'bg-gray-100 text-gray-600',
                'chargeback' => 'bg-red-100 text-red-700',
                default => 'bg-gray-100 text-gray-700',
            };

            $statusBadge = match ($tx->status->value) {
                'pending' => 'bg-yellow-100 text-yellow-700',
                'succeeded' => 'bg-green-100 text-green-700',
                'failed' => 'bg-red-100 text-red-700',
                'cancelled' => 'bg-gray-100 text-gray-500',
                default => 'bg-gray-100 text-gray-700',
            };

            $gwTxId = $tx->gateway_transaction_id ?? '';
            $gwShort = strlen($gwTxId) > 22 ? substr($gwTxId, 0, 22) . '…' : $gwTxId;

            $orderLink = $tx->order_id
                ? '<a href="' . route('admin.orders.show', $tx->order_id) . '" class="text-primary-600 hover:underline font-mono text-xs" target="_blank">' . e($tx->order_number ?? $tx->order_id) . '</a>'
                : '<span class="text-gray-300">—</span>';

            $customerLink = $tx->customer_id
                ? '<a href="' . route('admin.customers.show', $tx->customer_id) . '" class="text-primary-600 hover:underline text-sm">' . e($tx->customer_name ?? '—') . '</a>'
                : '<span class="text-gray-300">—</span>';

            $showUrl = route('admin.transactions.show', $tx->id);

            return [
                'DT_RowId' => 'tx-' . $tx->id,
                'gateway_transaction_id' => '<span class="font-mono text-xs cursor-pointer js-copy" data-value="' . e($gwTxId) . '" title="Click to copy">' . e($gwShort) . '</span>',
                'order' => $orderLink,
                'customer' => $customerLink,
                'type' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $typeBadge . '">' . e($tx->type->value) . '</span>',
                'gateway' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">' . e($tx->gateway) . '</span>',
                'amount' => '<span class="font-semibold tabular-nums text-sm">' . number_format($tx->amount / 100, 2) . ' ' . e($tx->currency) . '</span>',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($tx->status->value) . '</span>',
                'failure_code' => $tx->failure_code
                    ? '<span class="font-mono text-xs text-red-600" title="' . e($tx->failure_message) . '">' . e($tx->failure_code) . '</span>'
                    : '<span class="text-gray-300">—</span>',
                'processed_at' => $tx->processed_at
                    ? $tx->processed_at->format('M d, Y H:i')
                    : '<span class="text-gray-300">—</span>',
                'created_at' => $tx->created_at->format('M d, Y H:i'),
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">View</a>',
            ];
        });
    }

    public function show(PaymentTransaction $transaction): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.view'), 403);

        $transaction->load(['order', 'customer', 'refunds.approvedByAdmin']);

        return view('admin.transactions.show', compact('transaction'));
    }

    // ─── Refunds ──────────────────────────────────────────────────────────────

    public function refundIndex(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.view'), 403);

        $pendingCount = Refund::where('status', 'pending')->count();

        return view('admin.finance.refunds', compact('pendingCount'));
    }

    public function refundDatatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.view'), 403);

        $query = Refund::query()
            ->leftJoin('orders', 'orders.id', '=', 'refunds.order_id')
            ->leftJoin('customers', 'customers.id', '=', 'refunds.initiated_by_customer_id')
            ->select('refunds.*', 'orders.order_number', 'customers.name as customer_name');

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('refunds.status', $v),
            'date_from' => fn($q, $v) => $q->whereDate('refunds.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('refunds.created_at', '<=', $v),
        ]);

        $columns = [
            0 => ['searchable_columns' => ['orders.order_number'], 'orderable_column' => 'orders.order_number'],
            1 => ['searchable_columns' => ['customers.name'], 'orderable_column' => 'customers.name'],
            2 => ['orderable_column' => 'refunds.amount'],
            3 => [],
            4 => [],
            5 => [],
            6 => ['orderable_column' => 'refunds.status'],
            7 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($refund) {
            $statusBadge = match ($refund->status->value) {
                'pending' => 'bg-yellow-100 text-yellow-700',
                'approved' => 'bg-blue-100 text-blue-700',
                'processing' => 'bg-indigo-100 text-indigo-700',
                'completed' => 'bg-green-100 text-green-700',
                'rejected' => 'bg-red-100 text-red-700',
                'failed' => 'bg-red-100 text-red-700',
                default => 'bg-gray-100 text-gray-700',
            };

            $chargedBack = $refund->vendor_charged_back
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Yes</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">No</span>';

            $orderLink = $refund->order_id
                ? '<a href="' . route('admin.orders.show', $refund->order_id) . '" class="text-primary-600 hover:underline font-mono text-xs" target="_blank">' . e($refund->order_number ?? $refund->order_id) . '</a>'
                : '<span class="text-gray-300">—</span>';

            $actions = '';
            if ($refund->status === RefundStatus::Pending) {
                $approveUrl = route('admin.transactions.refunds.approve', $refund->id);
                $rejectUrl = route('admin.transactions.refunds.reject', $refund->id);
                $actions = '<div class="flex items-center gap-1">'
                    . '<button class="btn btn-xs btn-success js-approve-refund" data-url="' . $approveUrl . '">Approve</button>'
                    . '<button class="btn btn-xs btn-danger js-reject-refund" data-url="' . $rejectUrl . '" data-amount="' . number_format($refund->amount / 100, 2) . ' ' . e($refund->currency) . '">Reject</button>'
                    . '</div>';
            }

            return [
                'DT_RowId' => 'refund-' . $refund->id,
                'order' => $orderLink,
                'customer' => '<span class="text-sm">' . e($refund->customer_name ?? '—') . '</span>',
                'amount' => '<span class="font-semibold tabular-nums text-sm">' . number_format($refund->amount / 100, 2) . ' ' . e($refund->currency) . '</span>',
                'reason' => '<span class="text-sm text-gray-700">' . e($refund->reason->value) . '</span>',
                'refund_type' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">' . e(str_replace('_', ' ', $refund->refund_type->value)) . '</span>',
                'vendor_charged_back' => $chargedBack,
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($refund->status->value) . '</span>',
                'actions' => $actions,
            ];
        });
    }

    public function approveRefund(Request $request, Refund $refund): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.edit'), 403);

        if ($refund->status !== RefundStatus::Pending) {
            return response()->json(['message' => 'This refund is no longer pending.'], 422);
        }

        $refund->update([
            'status' => 'approved',
            'approved_by_admin_id' => $admin->id,
        ]);

        RefundProcessingJob::dispatch($refund);

        return response()->json(['message' => 'Refund approved and queued for processing.']);
    }

    public function rejectRefund(Request $request, Refund $refund): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('transactions.edit'), 403);

        if ($refund->status !== RefundStatus::Pending) {
            return response()->json(['message' => 'This refund is no longer pending.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $notes = $request->input('reason');

        $refund->update([
            'status' => 'rejected',
            'reason_notes' => $notes ? '[Admin rejection] ' . $notes : $refund->reason_notes,
        ]);

        return response()->json(['message' => 'Refund rejected.']);
    }
}
