<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Notifications\Customer\DisputeStatusChanged;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.view'), 403);

        if ($request->filled('export')) {
            return $this->exportDisputes($request);
        }

        $stats = [
            'open' => Dispute::where('status', 'open')->count(),
            'under_review' => Dispute::whereIn('status', ['seller_responded', 'under_review'])->count(),
            'escalated' => Dispute::where('status', 'escalated')->count(),
            'unassigned' => Dispute::whereNull('assigned_to_admin_id')
                ->whereNotIn('status', ['resolved', 'closed'])->count(),
            'resolved_30d' => Dispute::where('status', 'resolved')
                ->where('resolved_at', '>=', now()->subDays(30))->count(),
            'avg_resolution_hours' => (int) Dispute::whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
                ->value('avg_hours'),
        ];

        $admins = Admin::orderBy('name')->get(['id', 'name']);

        return view('admin.disputes.index', compact('stats', 'admins'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.view'), 403);

        $query = $this->buildDisputesQuery($request);

        $columns = [
            0 => ['searchable_columns' => ['disputes.dispute_number'], 'orderable_column' => 'disputes.dispute_number'],
            1 => ['searchable_columns' => ['orders.order_number']],
            2 => ['searchable_columns' => ['customers.name']],
            3 => ['searchable_columns' => ['vendors.store_name']],
            4 => ['orderable_column' => 'disputes.reason'],
            5 => ['orderable_column' => 'disputes.status'],
            6 => ['searchable_columns' => ['admins.name']],
            7 => ['orderable_column' => 'disputes.created_at'],
            8 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($d) {
            $statusBadge = $this->statusBadgeClass($d->status->value);
            $reasonLabel = __('admin.disputes_section.reason_' . $d->reason->value);
            $statusLabel = __('admin.disputes_section.' . $d->status->value);

            $isHot = in_array($d->status->value, ['open', 'escalated'], true)
                && \Carbon\Carbon::parse($d->created_at)->diffInHours(now()) > 48;

            $showUrl = route('admin.disputes.show', $d->id);

            $orderCell = $d->order_number
                ? '<a href="' . route('admin.orders.show', $d->order_id) . '" class="text-xs font-mono text-primary-600 hover:underline">' . e($d->order_number) . '</a>'
                : '<span class="text-xs text-gray-300">—</span>';

            return [
                'DT_RowId' => 'dsp-' . $d->id,
                'DT_RowClass' => $isHot ? 'bg-red-50' : '',
                'dispute_number' => '<a href="' . $showUrl . '" class="font-mono font-medium text-primary-600 hover:underline text-xs">' . e($d->dispute_number) . '</a>',
                'order' => $orderCell,
                'customer' => '<span class="text-sm text-gray-700">' . e($d->customer_name ?? '—') . '</span>',
                'vendor' => '<span class="text-sm text-gray-700">' . e($d->vendor_store_name ?? '—') . '</span>',
                'reason' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">' . e($reasonLabel) . '</span>',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($statusLabel) . '</span>',
                'assigned_to' => $d->assigned_admin_name
                    ? '<span class="text-xs text-gray-700">' . e($d->assigned_admin_name) . '</span>'
                    : '<span class="text-xs text-gray-300 italic">' . e(__('admin.disputes_section.unassigned')) . '</span>',
                'created_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . \Carbon\Carbon::parse($d->created_at)->format('M d, Y H:i') . '</span>',
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">' . e(__('common.view')) . '</a>',
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query builder (shared by datatable + export)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildDisputesQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Dispute::query()
            ->leftJoin('admins', 'admins.id', '=', 'disputes.assigned_to_admin_id')
            ->leftJoin('orders', 'orders.id', '=', 'disputes.order_id')
            ->leftJoin('customers', 'customers.id', '=', 'disputes.customer_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'disputes.vendor_id')
            ->select(
                'disputes.*',
                'admins.name as assigned_admin_name',
                'orders.order_number as order_number',
                'customers.name as customer_name',
                'vendors.store_name as vendor_store_name'
            );

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where('disputes.dispute_number', 'like', '%' . $v . '%'),
            'status' => fn($q, $v) => $q->where('disputes.status', $v),
            'reason' => fn($q, $v) => $q->where('disputes.reason', $v),
            'resolution' => fn($q, $v) => $q->where('disputes.resolution', $v),
            'assigned_to_admin_id' => fn($q, $v) => $q->where('disputes.assigned_to_admin_id', $v),
            'unassigned' => fn($q, $v) => $v ? $q->whereNull('disputes.assigned_to_admin_id') : $q,
            'date_from' => fn($q, $v) => $q->whereDate('disputes.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('disputes.created_at', '<=', $v),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function exportDisputes(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $disputes = $this->buildDisputesQuery($request)->orderByDesc('disputes.created_at')->get();

        $headers = ['Dispute #', 'Order #', 'Status', 'Reason', 'Date'];

        $rows = $disputes->map(fn($d) => [
            $d->dispute_number,
            $d->order_number,
            $d->status?->value,
            $d->reason?->value,
            optional($d->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('disputes', $headers, $rows),
            'csv' => $this->exportCsv('disputes', $headers, $rows),
            'word' => $this->exportWord('disputes', 'Disputes', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Dispute $dispute): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.view'), 403);

        $dispute->load([
            'messages' => fn($q) => $q->orderBy('created_at', 'asc'),
            'assignedToAdmin:id,name,email',
            'order:id,order_number,status,payment_status,total,currency',
            'subOrder:id,sub_order_number,status,subtotal,vendor_payout',
            'customer:id,name,email',
            'vendor:id,store_name,email',
            'returnRequest:id,return_number,status',
            'evidence',
        ]);

        $admins = Admin::orderBy('name')->get(['id', 'name']);

        return view('admin.disputes.show', compact('dispute', 'admin', 'admins'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reply (add admin message / internal note)
    // ─────────────────────────────────────────────────────────────────────────

    public function reply(Request $request, Dispute $dispute): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.manage'), 403);

        $data = $request->validate([
            'message' => 'required|string|max:10000',
            'is_internal_note' => 'boolean',
        ]);

        $isInternal = (bool) ($data['is_internal_note'] ?? false);

        DB::beginTransaction();
        try {
            DisputeMessage::create([
                'dispute_id' => $dispute->id,
                'sender_user_id' => $admin->id,
                'sender_role' => 'admin',
                'message' => $data['message'],
                'is_internal_note' => $isInternal,
                'created_at' => now(),
            ]);

            // Advance status from open → under_review on first admin (non-internal) reply
            $previousStatus = $dispute->status->value;
            if (!$isInternal && $dispute->status === DisputeStatus::Open) {
                $dispute->update(['status' => 'under_review']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => __('admin.disputes_section.reply_failed')], 500);
        }

        $dispute->refresh();

        if (isset($previousStatus) && $dispute->status->value !== $previousStatus) {
            $dispute->loadMissing('customer');
            $dispute->customer?->notify(new DisputeStatusChanged($dispute, $previousStatus));
        }

        return response()->json([
            'message' => $isInternal
                ? __('admin.disputes_section.internal_note_saved')
                : __('admin.disputes_section.reply_sent'),
            'status' => $dispute->status->value,
            'sender_name' => $admin->name,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Assign
    // ─────────────────────────────────────────────────────────────────────────

    public function assign(Request $request, Dispute $dispute): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.manage'), 403);

        $data = $request->validate([
            'admin_id' => 'nullable|uuid|exists:admins,id',
        ]);

        $dispute->update(['assigned_to_admin_id' => $data['admin_id'] ?? null]);

        $assigneeName = $data['admin_id']
            ? Admin::find($data['admin_id'])?->name
            : null;

        return response()->json([
            'message' => $assigneeName
                ? __('admin.disputes_section.assigned_to_message', ['name' => $assigneeName])
                : __('admin.disputes_section.unassigned_message'),
            'assignee_name' => $assigneeName,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Assign to me
    // ─────────────────────────────────────────────────────────────────────────

    public function assignMe(Dispute $dispute): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.manage'), 403);

        $dispute->update(['assigned_to_admin_id' => $admin->id]);

        return response()->json([
            'message' => __('admin.disputes_section.assigned_to_you'),
            'assignee_name' => $admin->name,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Status
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatus(Request $request, Dispute $dispute): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(DisputeStatus::class)],
        ]);

        $previousStatus = $dispute->status->value;

        $updates = ['status' => $data['status']];

        if (in_array($data['status'], ['resolved', 'closed'], true) && !$dispute->resolved_at) {
            $updates['resolved_at'] = now();
        } elseif (!in_array($data['status'], ['resolved', 'closed'], true)) {
            $updates['resolved_at'] = null;
        }

        $dispute->update($updates);

        if ($dispute->status->value !== $previousStatus) {
            $dispute->loadMissing('customer');
            $dispute->customer?->notify(new DisputeStatusChanged($dispute, $previousStatus));
        }

        return response()->json([
            'message' => __('admin.disputes_section.status_updated'),
            'status' => $data['status'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolve (set resolution, notes, compensation, mark resolved)
    // ─────────────────────────────────────────────────────────────────────────

    public function resolve(Request $request, Dispute $dispute): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('disputes.manage'), 403);

        $data = $request->validate([
            'resolution' => ['required', Rule::enum(DisputeResolution::class)],
            'resolution_notes' => 'nullable|string|max:5000',
            'compensation' => 'nullable|numeric|min:0|max:1000000',
            'close' => 'boolean',
        ]);

        $previousStatus = $dispute->status->value;

        DB::beginTransaction();
        try {
            $dispute->update([
                'resolution' => $data['resolution'],
                'resolution_notes' => $data['resolution_notes'] ?? null,
                'compensation' => isset($data['compensation'])
                    ? (int) round((float) $data['compensation'])
                    : null,
                'status' => !empty($data['close']) ? 'closed' : 'resolved',
                'resolved_at' => $dispute->resolved_at ?? now(),
                'assigned_to_admin_id' => $dispute->assigned_to_admin_id ?? $admin->id,
            ]);

            // System message in the thread
            DisputeMessage::create([
                'dispute_id' => $dispute->id,
                'sender_user_id' => $admin->id,
                'sender_role' => 'admin',
                'message' => sprintf(
                    "[%s] %s%s%s",
                    __('admin.disputes_section.resolution'),
                    __('admin.disputes_section.' . $data['resolution']),
                    isset($data['compensation']) && (float) $data['compensation'] > 0
                    ? ' — ' . __('admin.disputes_section.compensation_col') . ': ' . number_format((float) $data['compensation'], 2)
                    : '',
                    !empty($data['resolution_notes']) ? "\n" . $data['resolution_notes'] : ''
                ),
                'is_internal_note' => false,
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => __('admin.disputes_section.resolve_failed')], 500);
        }

        $dispute->refresh();

        if ($dispute->status->value !== $previousStatus) {
            $dispute->loadMissing('customer');
            $dispute->customer?->notify(new DisputeStatusChanged($dispute, $previousStatus));
        }

        return response()->json([
            'message' => __('admin.disputes_section.resolved_message'),
            'status' => $dispute->status->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'open' => 'bg-yellow-100 text-yellow-700',
            'seller_responded' => 'bg-blue-100 text-blue-700',
            'under_review' => 'bg-indigo-100 text-indigo-700',
            'escalated' => 'bg-red-100 text-red-700 border border-red-200',
            'resolved' => 'bg-green-100 text-green-700',
            'closed' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
