<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryAgentEarningStatus;
use App\Enums\DeliveryAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAgentEarning;
use App\Models\PaymentTransaction;
use App\Services\FileService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private readonly FileService $fileService,
        private readonly \App\Services\Delivery\AssignmentService $assignmentService,
    ) {
    }

    /** Today's assignments grouped by status (or filtered range when status/date filters are given). */
    public function index(Request $request): View|StreamedResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        if ($request->filled('export')) {
            return $this->exportAssignments($request, $agent);
        }

        $assignments = $this->buildAssignmentsQuery($request, $agent)
            ->with(['subOrder.order', 'subOrder.items', 'shipment'])
            ->orderByRaw("FIELD(status, 'assigned','accepted','picked_up','delivered','failed')")
            ->get();

        $pending = $assignments->whereIn('status', [
            DeliveryAssignmentStatus::Assigned,
            DeliveryAssignmentStatus::Accepted,
            DeliveryAssignmentStatus::PickedUp,
        ]);
        $completed = $assignments->where('status', DeliveryAssignment::STATUS_DELIVERED);
        $failed = $assignments->where('status', DeliveryAssignment::STATUS_FAILED);

        return view('delivery.assignments.index', compact('pending', 'completed', 'failed'));
    }

    /** Shared query for the index view and the export, scoped to the authenticated agent. */
    private function buildAssignmentsQuery(Request $request, DeliveryAgent $agent): Builder
    {
        $query = DeliveryAssignment::where('agent_id', $agent->id);

        // Default to "today" only when no explicit status/date range was requested.
        if (!$request->filled('status') && !$request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('assigned_at', today());
        }

        return $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('status', $v),
            'date_from' => fn ($q, $v) => $q->whereDate('assigned_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('assigned_at', '<=', $v),
        ]);
    }

    private function exportAssignments(Request $request, DeliveryAgent $agent): StreamedResponse
    {
        $assignments = $this->buildAssignmentsQuery($request, $agent)
            ->with(['subOrder.order', 'agent.zone'])
            ->orderByDesc('assigned_at')
            ->get();

        $headers = ['Assignment #', 'Order #', 'Status', 'Zone', 'Date'];

        $rows = $assignments->map(fn (DeliveryAssignment $a) => [
            $a->id,
            $a->subOrder?->order?->order_number ?? $a->subOrder?->sub_order_number ?? '—',
            $a->status?->value,
            $a->agent?->zone?->name ?? '—',
            $a->assigned_at?->format('Y-m-d H:i') ?? '—',
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('delivery-assignments', $headers, $rows),
            'csv' => $this->exportCsv('delivery-assignments', $headers, $rows),
            'word' => $this->exportWord('delivery-assignments', 'Delivery Assignments', $rows),
            default => abort(400, __('delivery.messages.common.invalid_export_format')),
        };
    }

    /** Full order details for one assignment. */
    public function show(DeliveryAssignment $assignment): View
    {
        $this->authorizeAssignment($assignment);

        $assignment->load([
            'subOrder.order.customer',
            'subOrder.items.productVariant' => fn ($q) => $q->withTrashed(),
            'subOrder.items.productVariant.product' => fn ($q) => $q->withTrashed(),
            'shipment',
        ]);

        return view('delivery.assignments.show', compact('assignment'));
    }

    /** Agent accepts the assignment (assigned → accepted). enhancement.md P-08: delegates to AssignmentService (same code path as the mobile app API). */
    public function accept(DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($assignment);

        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        try {
            $this->assignmentService->accept($assignment, $agent);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['message' => __('delivery.messages.assignments.cannot_accept_state')], 422);
        }

        return response()->json(['success' => true, 'message' => __('delivery.messages.assignments.accepted')]);
    }

    /** Agent marks item as picked up (accepted → picked_up). enhancement.md P-08: delegates to AssignmentService. */
    public function pickedUp(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($assignment);

        if ($assignment->status !== DeliveryAssignment::STATUS_ACCEPTED) {
            return response()->json(['message' => __('delivery.messages.assignments.not_accepted_state')], 422);
        }

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->assignmentService->pickup(
            $assignment,
            (float) ($validated['latitude'] ?? 0),
            (float) ($validated['longitude'] ?? 0),
        );

        return response()->json(['success' => true, 'message' => __('delivery.messages.assignments.marked_picked_up')]);
    }

    /**
     * Agent delivers — validates OTP, stores proof, updates all related records.
     *
     * enhancement.md P-08: this used to reimplement delivery independently of
     * the mobile app API (Services\Delivery\AssignmentService::deliver),
     * which meant COD was captured here but NOT on the mobile path, and
     * neither path updated order_items.fulfillment_status/orders.status
     * through validated transitions. Both panels now call the same
     * AssignmentService::deliver(), so the resulting DB state is identical.
     */
    public function deliver(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($assignment);

        if ($assignment->status !== DeliveryAssignment::STATUS_PICKED_UP) {
            return response()->json(['message' => __('delivery.messages.assignments.not_picked_up_state')], 422);
        }

        $assignment->load('subOrder.order');
        $order = $assignment->subOrder?->order;
        $isCod = $order && $order->payment_method === 'cod';

        $validated = $request->validate([
            'otp_code'             => ['required', 'digits:6'],
            'proof_image'          => ['nullable', 'image', 'max:5120'],
            'latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'            => ['nullable', 'numeric', 'between:-180,180'],
            'cod_amount_collected' => $isCod
                ? ['required', 'integer', 'min:1']
                : ['nullable', 'integer', 'min:1'],
            'discrepancy_note'     => ['nullable', 'string', 'max:500'],
        ]);

        // OTP validation (max 3 attempts)
        if ($assignment->otp_attempts >= 3) {
            return response()->json(['message' => __('delivery.messages.assignments.too_many_otp_attempts')], 422);
        }

        $expectedOtp = $assignment->delivery_otp
            ?? ($assignment->shipment?->delivery_otp ?? null);

        if ($expectedOtp && $validated['otp_code'] !== (string) $expectedOtp) {
            $assignment->increment('otp_attempts');
            $remaining = 3 - $assignment->fresh()->otp_attempts;

            return response()->json([
                'message' => __('delivery.messages.assignments.invalid_otp_remaining', ['remaining' => $remaining]),
                'remaining' => $remaining,
            ], 422);
        }

        $agent = Auth::guard('delivery')->user();

        try {
            $this->assignmentService->deliver(
                $assignment,
                $agent,
                $validated['otp_code'],
                $request->file('proof_image'),
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                isset($validated['cod_amount_collected']) ? (int) $validated['cod_amount_collected'] : null,
                $validated['discrepancy_note'] ?? null,
            );
        } catch (\DomainException $e) {
            return match ($e->getMessage()) {
                'cod_amount_required' => response()->json(['message' => __('delivery.messages.assignments.cod_amount_mismatch', ['collected' => 0, 'expected' => 0])], 422),
                'discrepancy_note_required' => response()->json([
                    'message' => __('delivery.messages.assignments.cod_amount_mismatch', [
                        'collected' => (string) ($validated['cod_amount_collected'] ?? 0),
                        'expected' => (string) ($order?->total ?? 0),
                    ]),
                    'requires_discrepancy_note' => true,
                ], 422),
                default => response()->json(['message' => $e->getMessage()], 422),
            };
        } catch (\RuntimeException $e) {
            return response()->json(['message' => __('delivery.messages.assignments.invalid_otp_remaining', ['remaining' => 0])], 422);
        }

        return response()->json(['success' => true, 'message' => __('delivery.messages.assignments.delivery_confirmed')]);
    }

    /** Agent marks delivery as failed. enhancement.md P-08: delegates to AssignmentService (RTO logic included). */
    public function fail(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($assignment);

        if (
            !in_array($assignment->status, [
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
        ) {
            return response()->json(['message' => __('delivery.messages.assignments.cannot_fail_state')], 422);
        }

        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'max:255'],
            'failure_notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'customer_rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->assignmentService->fail(
                $assignment,
                $validated['failure_reason'],
                $validated['failure_notes'] ?? null,
                (float) ($validated['latitude'] ?? 0),
                (float) ($validated['longitude'] ?? 0),
                $validated['customer_rejection_reason'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('delivery.messages.assignments.marked_failed')]);
    }

    /** Ensures the assignment belongs to the authenticated agent. */
    private function authorizeAssignment(DeliveryAssignment $assignment): void
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        abort_if($assignment->agent_id !== $agent->id, 403, __('delivery.messages.common.forbidden'));
    }
}
