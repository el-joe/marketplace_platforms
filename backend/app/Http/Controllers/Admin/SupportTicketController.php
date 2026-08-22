<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.view'), 403);

        if ($request->filled('export')) {
            return $this->exportTickets($request);
        }

        $stats = [
            'open' => SupportTicket::where('status', SupportTicketStatus::Open)->count(),
            'in_progress' => SupportTicket::where('status', SupportTicketStatus::InProgress)->count(),
            'urgent' => SupportTicket::where('priority', 'urgent')->whereNotIn('status', [SupportTicketStatus::Resolved, SupportTicketStatus::Closed])->count(),
            'unassigned' => SupportTicket::whereNull('assigned_to_admin_id')->whereNotIn('status', [SupportTicketStatus::Resolved, SupportTicketStatus::Closed])->count(),
            'avg_first_response_minutes' => (int) SupportTicket::whereNotNull('first_response_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_minutes')
                ->value('avg_minutes'),
        ];

        $admins = Admin::orderBy('name')->get(['id', 'name']);

        return view('admin.support-tickets.index', compact('stats', 'admins'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.view'), 403);

        $query = $this->buildTicketsQuery($request);

        $columns = [
            0 => ['searchable_columns' => ['support_tickets.ticket_number'], 'orderable_column' => 'support_tickets.ticket_number'],
            1 => ['searchable_columns' => ['support_tickets.requester_user_id']],
            2 => ['orderable_column' => 'support_tickets.category'],
            3 => ['orderable_column' => 'support_tickets.priority'],
            4 => ['orderable_column' => 'support_tickets.status'],
            5 => ['searchable_columns' => ['support_tickets.subject']],
            6 => ['searchable_columns' => ['admins.name']],
            7 => [],
            8 => ['orderable_column' => 'support_tickets.created_at'],
            9 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($t) {
            $priorityBadge = match ($t->priority) {
                'urgent' => 'bg-red-100 text-red-700 border border-red-200',
                'high' => 'bg-orange-100 text-orange-700',
                'normal' => 'bg-blue-100 text-blue-700',
                'low' => 'bg-gray-100 text-gray-500',
                default => 'bg-gray-100 text-gray-500',
            };
            $statusBadge = match ($t->status) {
                SupportTicketStatus::Open => 'bg-yellow-100 text-yellow-700',
                SupportTicketStatus::InProgress => 'bg-blue-100 text-blue-700',
                SupportTicketStatus::WaitingCustomer => 'bg-indigo-100 text-indigo-700',
                SupportTicketStatus::Resolved => 'bg-green-100 text-green-700',
                SupportTicketStatus::Closed => 'bg-gray-100 text-gray-500',
                default => 'bg-gray-100 text-gray-500',
            };
            $categoryBadge = 'bg-gray-100 text-gray-600';

            $statusLabelKey = $t->status === SupportTicketStatus::WaitingCustomer ? 'waiting' : $t->status->value;

            $roleLabel = $t->requester_role === SupportTicketRequesterRole::Seller
                ? __('admin.support_tickets.vendor_seller')
                : __('admin.support_tickets.customer');
            $roleBadge = $t->requester_role === SupportTicketRequesterRole::Seller ? 'bg-purple-100 text-purple-700' : 'bg-teal-100 text-teal-700';

            // Response time
            $responseTime = '—';
            if ($t->first_response_at) {
                $mins = \Carbon\Carbon::parse($t->created_at)->diffInMinutes($t->first_response_at);
                $responseTime = $mins < 60 ? "{$mins}m" : round($mins / 60, 1) . 'h';
            }

            $showUrl = route('admin.support-tickets.show', $t->id);

            return [
                'DT_RowId' => 'st-' . $t->id,
                'DT_RowClass' => $t->priority === 'urgent' && !in_array($t->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true) ? 'bg-red-50' : '',
                'ticket_number' => '<a href="' . $showUrl . '" class="font-mono font-medium text-primary-600 hover:underline text-xs">' . e($t->ticket_number) . '</a>',
                'requester' => '<div class="flex items-center gap-1.5"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ' . $roleBadge . '">' . $roleLabel . '</span><span class="text-xs font-mono text-gray-500">' . e(substr($t->requester_user_id, 0, 8)) . '</span></div>',
                'category' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $categoryBadge . '">' . e(__('admin.support_tickets.category_' . $t->category)) . '</span>',
                'priority' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $priorityBadge . '">' . e(__('admin.support_tickets.' . $t->priority)) . '</span>',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e(__('admin.support_tickets.' . $statusLabelKey)) . '</span>',
                'subject' => '<span class="text-sm text-gray-800">' . e(\Illuminate\Support\Str::limit($t->subject, 60)) . '</span>',
                'assigned_to' => $t->assigned_admin_name ? '<span class="text-xs text-gray-700">' . e($t->assigned_admin_name) . '</span>' : '<span class="text-xs text-gray-300 italic">' . e(__('admin.support_tickets.unassigned')) . '</span>',
                'response_time' => '<span class="text-xs tabular-nums ' . ($t->first_response_at ? 'text-gray-600' : 'text-orange-500') . '">' . $responseTime . '</span>',
                'created_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . \Carbon\Carbon::parse($t->created_at)->format('M d, Y H:i') . '</span>',
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">' . e(__('admin.view')) . '</a>',
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query builder (shared by datatable + export)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildTicketsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = SupportTicket::query()
            ->leftJoin('admins', 'admins.id', '=', 'support_tickets.assigned_to_admin_id')
            ->select('support_tickets.*', 'admins.name as assigned_admin_name')
            ->addSelect(['last_reply_at' => TicketMessage::selectRaw('MAX(created_at)')
                ->whereColumn('ticket_id', 'support_tickets.id')]);

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('support_tickets.ticket_number', 'like', '%' . $v . '%')
                    ->orWhere('support_tickets.subject', 'like', '%' . $v . '%');
            }),
            'status' => fn($q, $v) => $q->where('support_tickets.status', $v),
            'category' => fn($q, $v) => $q->where('support_tickets.category', $v),
            'priority' => fn($q, $v) => $q->where('support_tickets.priority', $v),
            'assigned_to_admin_id' => fn($q, $v) => $q->where('support_tickets.assigned_to_admin_id', $v),
            'requester_role' => fn($q, $v) => $q->where('support_tickets.requester_role', $v),
            'unassigned' => fn($q, $v) => $v ? $q->whereNull('support_tickets.assigned_to_admin_id') : $q,
            'date_from' => fn($q, $v) => $q->whereDate('support_tickets.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('support_tickets.created_at', '<=', $v),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function exportTickets(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tickets = $this->buildTicketsQuery($request)->orderByDesc('support_tickets.created_at')->get();

        $headers = ['Ticket #', 'Subject', 'Status', 'Priority', 'Created', 'Last Reply'];

        $rows = $tickets->map(fn($t) => [
            $t->ticket_number,
            $t->subject,
            $t->status?->value,
            $t->priority,
            optional($t->created_at)->format('d M Y H:i'),
            $t->last_reply_at ? \Carbon\Carbon::parse($t->last_reply_at)->format('d M Y H:i') : '',
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('support-tickets', $headers, $rows),
            'csv' => $this->exportCsv('support-tickets', $headers, $rows),
            'word' => $this->exportWord('support-tickets', 'Support Tickets', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(SupportTicket $ticket): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.view'), 403);

        $ticket->load([
            'messages' => fn($q) => $q->orderBy('created_at', 'asc'),
            'assignedToAdmin:id,name,email',
            'relatedOrder:id,order_number,status',
            'relatedProduct:id,name_en',
        ]);

        // Load requester info based on role
        if ($ticket->requester_role === SupportTicketRequesterRole::Seller) {
            $ticket->load('requesterVendor:id,store_name,email');
        } else {
            $ticket->load('requesterCustomer:id,name,email');
        }

        $admins = Admin::orderBy('name')->get(['id', 'name']);

        return view('admin.support-tickets.show', compact('ticket', 'admin', 'admins'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reply
    // ─────────────────────────────────────────────────────────────────────────

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.reply'), 403);

        $data = $request->validate([
            'message' => 'required|string|max:10000',
            'is_internal_note' => 'boolean',
        ]);

        $isInternal = (bool) ($data['is_internal_note'] ?? false);

        DB::beginTransaction();
        try {
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_type' => Admin::class,
                'sender_id' => $admin->id,
                'message' => $data['message'],
                'is_internal_note' => $isInternal,
                'is_ai_generated' => false,
            ]);

            $updates = [];

            // Set first_response_at on first non-internal reply
            if (!$isInternal && !$ticket->first_response_at) {
                $updates['first_response_at'] = now();
            }

            // Advance status on non-internal reply
            if (!$isInternal && $ticket->status === SupportTicketStatus::Open) {
                $updates['status'] = SupportTicketStatus::InProgress;
            }

            if (!empty($updates)) {
                $ticket->update($updates);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => __('admin.support_tickets.failed_send_reply')], 500);
        }

        return response()->json(['message' => __('admin.support_tickets.reply_sent'), 'status' => $ticket->fresh()->status?->value]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Assign
    // ─────────────────────────────────────────────────────────────────────────

    public function assign(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.assign'), 403);

        $data = $request->validate([
            'admin_id' => 'nullable|uuid|exists:admins,id',
        ]);

        $ticket->update(['assigned_to_admin_id' => $data['admin_id'] ?? null]);

        $assigneeName = $data['admin_id']
            ? Admin::find($data['admin_id'])?->name
            : null;

        return response()->json([
            'message' => $assigneeName
                ? __('admin.support_tickets.assigned_to_message', ['name' => $assigneeName])
                : __('admin.support_tickets.unassigned_message'),
            'assignee_name' => $assigneeName,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Assign to me
    // ─────────────────────────────────────────────────────────────────────────

    public function assignMe(SupportTicket $ticket): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.assign'), 403);

        $ticket->update(['assigned_to_admin_id' => $admin->id]);

        return response()->json(['message' => __('admin.support_tickets.ticket_assigned_to_you'), 'assignee_name' => $admin->name]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Status
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.reply'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(SupportTicketStatus::class)],
        ]);

        $status = SupportTicketStatus::from($data['status']);

        $updates = ['status' => $status];

        if ($status === SupportTicketStatus::Resolved && !$ticket->resolved_at) {
            $updates['resolved_at'] = now();
        } elseif (in_array($status, [SupportTicketStatus::Open, SupportTicketStatus::InProgress, SupportTicketStatus::WaitingCustomer], true)) {
            $updates['resolved_at'] = null;
        }

        $ticket->update($updates);

        return response()->json(['message' => __('admin.support_tickets.status_updated'), 'status' => $data['status']]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Priority
    // ─────────────────────────────────────────────────────────────────────────

    public function updatePriority(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('support.reply'), 403);

        $data = $request->validate([
            'priority' => 'required|in:low,normal,high,urgent',
        ]);

        $ticket->update(['priority' => $data['priority']]);

        return response()->json(['message' => __('admin.support_tickets.priority_updated'), 'priority' => $data['priority']]);
    }
}
