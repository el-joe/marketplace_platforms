<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrier\Agent\ReassignAgentRequest;
use App\Http\Requests\Carrier\Agent\StoreAgentRequest;
use App\Http\Requests\Carrier\Agent\UpdateAgentRequest;
use App\Http\Resources\Carrier\AgentPerformanceResource;
use App\Http\Resources\Carrier\CarrierAgentResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\ShippingCompanySupervisor;
use App\Services\Carrier\AgentRosterService;
use App\Services\Carrier\AssignmentReassignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(
        private readonly AgentRosterService $rosterService,
        private readonly AssignmentReassignmentService $reassignmentService,
    ) {}

    // ── GET /agents ───────────────────────────────────────────────────────────
    // Permission: manage_agents
    // Scoped strictly to the supervisor's company; platform agents (NULL company)
    // are excluded at the query level — never returned regardless of other filters.

    public function index(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        // Compat shim: falls back to the company's home country until the
        // supervisor is backfilled with their own country_id.
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $query = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->with('zone')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->input('zone_id'));
        }

        $agents = $query->paginate(20);

        return ApiResponse::paginated($agents, CarrierAgentResource::class);
    }

    // ── POST /agents ──────────────────────────────────────────────────────────
    // Permission: manage_agents

    public function store(StoreAgentRequest $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->rosterService->createAgent(
            $supervisor->company,
            $supervisor,
            $request->validated(),
        );

        return ApiResponse::success(
            new CarrierAgentResource($agent->load('zone')),
            'Agent created successfully. They are inactive until document verification is complete.',
            201,
        );
    }

    // ── GET /agents/{id} ──────────────────────────────────────────────────────
    // Permission: manage_agents
    // Returns 404 for agents outside the company to avoid leaking existence.

    public function show(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->resolveOwnAgent($id, $supervisor);

        return ApiResponse::success(
            new CarrierAgentResource($agent->load(['zone', 'documents'])),
        );
    }

    // ── PUT /agents/{id} ─────────────────────────────────────────────────────
    // Permission: manage_agents
    // agent_type and shipping_company_id are structural — not editable here.

    public function update(UpdateAgentRequest $request, string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->resolveOwnAgent($id, $supervisor);

        $data = $request->validated();

        // Map API field name to actual column name.
        if (array_key_exists('license_plate', $data)) {
            $data['vehicle_plate'] = $data['license_plate'];
            unset($data['license_plate']);
        }

        $agent->update($data);

        return ApiResponse::success(
            new CarrierAgentResource($agent->fresh()->load('zone')),
            'Agent updated.',
        );
    }

    // ── DELETE /agents/{id} ───────────────────────────────────────────────────
    // Permission: manage_agents
    // Soft-delete blocked if agent has active (accepted/picked_up) assignments.

    public function destroy(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->resolveOwnAgent($id, $supervisor);

        $activeCount = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereIn('status', [
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
            ->count();

        if ($activeCount > 0) {
            return ApiResponse::error(
                'This agent has ' . $activeCount . ' active assignment(s) in progress and cannot be removed.',
                [],
                409,
            );
        }

        $agent->delete();

        return ApiResponse::success(null, 'Agent removed.');
    }

    // ── GET /agents/{id}/documents ────────────────────────────────────────────
    // Permission: manage_agents
    // Read-only: document verification is admin-only (platform oversight is retained).

    public function documents(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->resolveOwnAgent($id, $supervisor);

        $docs = $agent->documents()->orderBy('document_type')->get()->map(fn ($doc) => [
            'id'               => $doc->id,
            'document_type'    => $doc->document_type->value,
            'status'           => $doc->status->value,
            'expires_at'       => $doc->expires_at?->toDateString(),
            'verified_at'      => $doc->verified_at?->toIso8601String(),
            'rejection_reason' => $doc->rejection_reason,
        ]);

        return ApiResponse::success($docs);
    }

    // ── GET /agents/{id}/assignments ──────────────────────────────────────────
    // Permission: view_orders
    // Read-only view of this agent's delivery history.

    public function assignments(Request $request, string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->resolveOwnAgent($id, $supervisor);

        $assignments = DeliveryAssignment::where('agent_id', $agent->id)
            ->orderBy('assigned_at', 'desc')
            ->paginate(20);

        $items = $assignments->getCollection()->map(fn ($a) => [
            'id'          => $a->id,
            'status'      => $a->status->value,
            'assigned_at' => $a->assigned_at->toIso8601String(),
            'delivered_at'=> $a->delivered_at?->toIso8601String(),
            'failed_at'   => $a->failed_at?->toIso8601String(),
            'failure_reason' => $a->failure_reason,
            'customer_rating' => $a->customer_rating,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'items' => $items,
                'meta'  => [
                    'current_page' => $assignments->currentPage(),
                    'last_page'    => $assignments->lastPage(),
                    'per_page'     => $assignments->perPage(),
                    'total'        => $assignments->total(),
                ],
            ],
        ]);
    }

    // ── GET /agents/{id}/performance ──────────────────────────────────────────
    // Permission: view_reports

    public function performance(Request $request, string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->resolveOwnAgent($id, $supervisor);

        $days      = (int) $request->input('days', 30);
        $allowedPeriods = [30, 60, 90];
        if (! in_array($days, $allowedPeriods, true)) {
            $days = 30;
        }
        $from = now()->subDays($days)->startOfDay();
        $to   = now()->endOfDay();

        $assignments = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereBetween('assigned_at', [$from, $to])
            ->get();

        $total    = $assignments->count();
        $delivered = $assignments->where('status', DeliveryAssignment::STATUS_DELIVERED)->count();
        $failed    = $assignments->where('status', DeliveryAssignment::STATUS_FAILED)->count();

        // On-time: delivered before or on expected_at (column may not exist yet).
        // Using delivered_at vs assigned_at as a proxy: under 24 hours is on-time.
        $onTime = $assignments
            ->filter(fn ($a) => $a->status === DeliveryAssignment::STATUS_DELIVERED
                && $a->delivered_at !== null
                && $a->assigned_at->diffInHours($a->delivered_at) <= 24)
            ->count();

        $data = [
            'agent_id'             => $agent->id,
            'period_days'          => $days,
            'period_from'          => $from->toDateString(),
            'period_to'            => $to->toDateString(),
            'total_deliveries'     => $total,
            'rating_avg'           => round((float) $agent->rating_avg, 2),
            'on_time_rate'         => $delivered > 0 ? round($onTime / $delivered * 100, 1) : null,
            'failed_delivery_rate' => $total > 0 ? round($failed / $total * 100, 1) : null,
        ];

        return ApiResponse::success(new AgentPerformanceResource($data));
    }

    // ── POST /assignments/{id}/reassign ───────────────────────────────────────
    // Permission: assign_agents

    public function reassign(ReassignAgentRequest $request, string $assignmentId): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        // 404 (not 403) if assignment's current agent is outside the company scope.
        $assignment = DeliveryAssignment::findOrFail($assignmentId);
        $newAgent   = DeliveryAgent::findOrFail($request->validated()['new_delivery_agent_id']);

        $updated = $this->reassignmentService->reassign($assignment, $newAgent, $supervisor);

        return ApiResponse::success([
            'assignment_id'   => $updated->id,
            'new_agent_id'    => $updated->agent_id,
            'status'          => $updated->status->value,
        ], __('carrier.api.assignment_reassigned'));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve a DeliveryAgent by ID and abort with 404 if it is outside the
     * supervisor's company scope. Never returns 403 to avoid leaking existence
     * of agents belonging to other companies or platform-employed agents.
     */
    private function resolveOwnAgent(string $id, ShippingCompanySupervisor $supervisor): DeliveryAgent
    {
        $agent = DeliveryAgent::find($id);

        // Compat shim: falls back to the company's home country until the
        // supervisor is backfilled with their own country_id.
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        if (
            $agent === null
            || $agent->shipping_company_id === null              // platform agent — absolutely off-limits
            || $agent->shipping_company_id !== $supervisor->shipping_company_id
            || ($countryId && $agent->country_id !== $countryId)
        ) {
            abort(404);
        }

        return $agent;
    }
}
