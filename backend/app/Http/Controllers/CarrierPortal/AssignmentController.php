<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryZone;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Notifications\DeliveryAgent\DeliveryReassigned;
use App\Notifications\DeliveryAgent\NewDeliveryAssigned;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function index(Request $request): View|StreamedResponse
    {
        $supervisor = auth('shipping_supervisor')->user();

        abort_unless($supervisor->hasPermission('view_orders'), 403, __('carrier.errors.no_permission_view_orders'));

        if ($request->filled('export')) {
            return $this->exportAssignments($request, $supervisor);
        }

        $assignments = $this->buildAssignmentsQuery($request, $supervisor)
            ->with('agent')
            ->latest('assigned_at')
            ->paginate(25);

        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $agents = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->active()
            ->get(['id', 'name', 'vehicle_type', 'rating_avg']);

        return view('carrier.assignments.index', compact('assignments', 'agents'));
    }

    /** Shared query for the index view and the export, scoped to the supervisor's company. */
    private function buildAssignmentsQuery(Request $request, $supervisor): Builder
    {
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $agentIds = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->pluck('id');

        $query = DeliveryAssignment::whereIn('agent_id', $agentIds);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('agent', fn ($q) => $q->where('name', 'like', '%' . $search . '%'));
        }

        return $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('status', $v),
            'date_from' => fn ($q, $v) => $q->whereDate('assigned_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('assigned_at', '<=', $v),
        ]);
    }

    private function exportAssignments(Request $request, $supervisor): StreamedResponse
    {
        $assignments = $this->buildAssignmentsQuery($request, $supervisor)
            ->with('agent')
            ->latest('assigned_at')
            ->get();

        $headers = ['Assignment #', 'Agent', 'Status', 'Date'];

        $rows = $assignments->map(fn (DeliveryAssignment $a) => [
            $a->id,
            $a->agent?->name ?? '—',
            $a->status?->value,
            $a->assigned_at?->format('Y-m-d H:i') ?? '—',
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('carrier-assignments', $headers, $rows),
            'csv' => $this->exportCsv('carrier-assignments', $headers, $rows),
            'word' => $this->exportWord('carrier-assignments', 'Carrier Assignments', $rows),
            default => abort(400, __('carrier.errors.invalid_export_format')),
        };
    }

    public function show(string $assignmentId): View
    {
        $supervisor = auth('shipping_supervisor')->user();

        abort_unless($supervisor->hasPermission('view_orders'), 403, __('carrier.errors.no_permission_view_orders'));

        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $assignment = DeliveryAssignment::query()
            ->whereHas('agent', fn ($q) => $q->where('shipping_company_id', $supervisor->shipping_company_id)
                ->when($countryId, fn ($q2) => $q2->where('country_id', $countryId)))
            ->with([
                'agent',
                'shipment.subOrder.order.customer',
                'shipment.subOrder.items',
                'shipment.trackingEvents' => fn ($q) => $q->orderBy('occurred_at'),
            ])
            ->findOrFail($assignmentId);

        $availableAgents = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->where('status', 'active')
            ->where('is_available', true)
            ->where('id', '!=', $assignment->agent_id)
            ->get(['id', 'name', 'vehicle_type', 'rating_avg']);

        return view('carrier.assignments.show', compact('assignment', 'availableAgents'));
    }

    public function reassign(Request $request, string $assignmentId): JsonResponse
    {
        $supervisor = auth('shipping_supervisor')->user();

        abort_unless($supervisor->hasPermission('assign_orders'), 403, __('carrier.errors.no_permission_reassign'));

        $request->validate(['new_agent_id' => 'required|string|exists:delivery_agents,id']);

        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $assignment = DeliveryAssignment::query()
            ->whereHas('agent', fn ($q) => $q->where('shipping_company_id', $supervisor->shipping_company_id)
                ->when($countryId, fn ($q2) => $q2->where('country_id', $countryId)))
            ->findOrFail($assignmentId);

        if (!in_array($assignment->status?->value, ['assigned', 'accepted'])) {
            return response()->json(['success' => false, 'message' => __('carrier.assignments.cannot_reassign_picked_up')], 422);
        }

        // Scope new agent to same company and country — prevents assigning to
        // another company's agent, or an agent outside this supervisor's country.
        $newAgent = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->where('id', $request->new_agent_id)
            ->where('status', 'active')
            ->firstOrFail();

        $oldAgentId = $assignment->agent_id;

        DB::transaction(function () use ($assignment, $newAgent) {
            $assignment->update([
                'agent_id'    => $newAgent->id,
                'status'      => 'assigned',
                'assigned_at' => now(),
                'accepted_at' => null,
            ]);
            // NOTE: No dedicated reassignment audit table exists in this schema.
            // The reassignment is reflected in the assignment record itself (agent_id, assigned_at reset).
            // Consider adding an assignment_history table for a proper audit trail.
        });

        $oldAgent = DeliveryAgent::find($oldAgentId);
        if ($oldAgent) {
            $oldAgent->notify(new DeliveryReassigned($assignment, $oldAgentId));
        }
        $newAgent->notify(new NewDeliveryAssigned($assignment));

        return response()->json(['success' => true, 'message' => __('carrier.assignments.reassigned_success')]);
    }

    public function unassigned(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();

        abort_unless($supervisor->hasPermission('view_orders'), 403, __('carrier.errors.no_permission_view_orders'));

        // shipping_carriers.shipping_company_id links each carrier integration (e.g. Aramex API)
        // to the local ShippingCompany that fulfils it, so unassigned shipments are scoped to
        // the carriers linked to this supervisor's company.
        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');

        $noCarriersLinked = $carrierIds->isEmpty();

        // Compat shim: falls back to the company's home country until the
        // supervisor is backfilled with their own country_id.
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        // City IDs covered by this company's active agents' zones — narrows
        // unassigned shipments to those actually deliverable by this company,
        // instead of every shipment in the country.
        $cityIds = DeliveryZone::whereHas('agents', fn ($q) =>
                $q->where('shipping_company_id', $supervisor->shipping_company_id)
                    ->when($countryId, fn ($q2) => $q2->where('country_id', $countryId))
            )
            ->pluck('city_ids')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $shipments = $noCarriersLinked
            ? Shipment::where('id', null)->paginate(20)
            : Shipment::whereDoesntHave('deliveryAssignment')
                ->where('status', '!=', ShipmentStatus::Delivered)
                ->whereIn('carrier_id', $carrierIds)
                ->whereHas('subOrder.order', fn ($q) =>
                    $q->where('country_id', $countryId)
                )
                ->when($cityIds->isNotEmpty(), fn ($q) =>
                    $q->whereHas('subOrder.order', fn ($q2) =>
                        $q2->whereIn(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(shipping_address_snapshot, '$.city_id'))"),
                            $cityIds
                        )
                    )
                )
                ->with('subOrder.order')
                ->latest()
                ->paginate(20);

        $agents = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->where('status', 'active')
            ->get(['id', 'name', 'vehicle_type', 'is_available', 'rating_avg']);

        return view('carrier.assignments.unassigned', compact('shipments', 'agents', 'noCarriersLinked'));
    }

    public function assign(Request $request, string $shipmentId): JsonResponse
    {
        $supervisor = auth('shipping_supervisor')->user();

        abort_unless($supervisor->hasPermission('assign_orders'), 403, __('carrier.errors.no_permission_assign'));

        $request->validate(['agent_id' => 'required|string|exists:delivery_agents,id']);

        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $shipment = Shipment::whereDoesntHave('deliveryAssignment')
            ->whereIn('carrier_id', $carrierIds)
            ->with('subOrder.order')
            ->findOrFail($shipmentId);

        $agent = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->where('id', $request->agent_id)
            ->where('status', 'active')
            ->firstOrFail();

        $orderCountryId = $shipment->subOrder?->order?->country_id;
        $agentCountryId = $agent->country_id;

        if ($orderCountryId && $agentCountryId && $orderCountryId !== $agentCountryId) {
            return response()->json([
                'success' => false,
                'message' => 'Agent does not operate in the order\'s country.',
            ], 422);
        }

        $assignment = null;

        DB::transaction(function () use ($shipment, $agent, &$assignment) {
            $assignment = DeliveryAssignment::create([
                'shipment_id'  => $shipment->id,
                'sub_order_id' => $shipment->sub_order_id,
                'agent_id'     => $agent->id,
                'status'       => 'assigned',
                'assigned_at'  => now(),
                'delivery_otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            ]);
        });

        $agent->notify(new NewDeliveryAssigned($assignment));

        return response()->json(['success' => true, 'message' => __('carrier.assignments.assign_success')]);
    }
}
