<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryZone;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Models\ShippingCompanySupervisor;
use App\Notifications\DeliveryAgent\NewDeliveryAssigned;
use App\Services\Carrier\AssignmentReassignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentReassignmentService $reassignmentService)
    {
    }

    public function unassigned(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_orders');

        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');
        $noCarriersLinked = $carrierIds->isEmpty();

        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

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
                ->whereHas('subOrder.order', fn ($q) => $q->where('country_id', $countryId))
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
                ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'no_carriers_linked' => $noCarriersLinked,
                'items' => collect($shipments->items())->map(fn (Shipment $s) => [
                    'id'               => $s->id,
                    'tracking_number'  => $s->tracking_number,
                    'sub_order_number' => $s->subOrder?->sub_order_number,
                    'order_number'     => $s->subOrder?->order?->order_number,
                    'status'           => $s->status?->value,
                    'created_at'       => $s->created_at?->toIso8601String(),
                ]),
                'meta' => [
                    'current_page' => $shipments->currentPage(),
                    'last_page'    => $shipments->lastPage(),
                    'per_page'     => $shipments->perPage(),
                    'total'        => $shipments->total(),
                ],
            ],
        ]);
    }

    public function assign(Request $request, string $shipment): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'assign_orders');

        $request->validate(['agent_id' => 'required|string|exists:delivery_agents,id']);

        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');
        $countryId  = $supervisor->country_id ?? $supervisor->company?->country_id;

        $shipmentModel = Shipment::whereDoesntHave('deliveryAssignment')
            ->whereIn('carrier_id', $carrierIds)
            ->with('subOrder.order')
            ->findOrFail($shipment);

        $agent = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->where('id', $request->input('agent_id'))
            ->where('status', 'active')
            ->firstOrFail();

        $orderCountryId = $shipmentModel->subOrder?->order?->country_id;
        $agentCountryId = $agent->country_id;

        if ($orderCountryId && $agentCountryId && $orderCountryId !== $agentCountryId) {
            return ApiResponse::error("Agent does not operate in the order's country.", [], 422);
        }

        $assignment = null;

        DB::transaction(function () use ($shipmentModel, $agent, &$assignment) {
            $assignment = DeliveryAssignment::create([
                'shipment_id'  => $shipmentModel->id,
                'sub_order_id' => $shipmentModel->sub_order_id,
                'agent_id'     => $agent->id,
                'status'       => 'assigned',
                'assigned_at'  => now(),
                'delivery_otp' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            ]);
        });

        $agent->notify(new NewDeliveryAssigned($assignment));

        return ApiResponse::success(['assignment' => $this->transform($assignment)], 'Shipment assigned.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_orders');

        $query = $this->scopedQuery($supervisor);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('agent', fn ($q) => $q->where('name', 'like', '%' . $search . '%'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('assigned_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('assigned_at', '<=', $request->input('date_to'));
        }

        $assignments = $query->with('agent', 'subOrder')
            ->latest('assigned_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($assignments->items())->map(fn (DeliveryAssignment $a) => $this->transform($a)),
                'meta' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page'    => $assignments->lastPage(),
                    'per_page'     => $assignments->perPage(),
                    'total'        => $assignments->total(),
                ],
            ],
        ]);
    }

    public function show(string $assignment): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_orders');

        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $model = DeliveryAssignment::query()
            ->whereHas('agent', fn ($q) => $q->where('shipping_company_id', $supervisor->shipping_company_id)
                ->when($countryId, fn ($q2) => $q2->where('country_id', $countryId)))
            ->with([
                'agent',
                'shipment.subOrder.order.customer',
                'shipment.subOrder.items',
                'shipment.trackingEvents' => fn ($q) => $q->orderBy('occurred_at'),
            ])
            ->findOrFail($assignment);

        return ApiResponse::success(['assignment' => $this->transform($model, detailed: true)]);
    }

    public function reassign(Request $request, string $assignment): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'assign_orders');

        $request->validate(['new_delivery_agent_id' => 'required|string|exists:delivery_agents,id']);

        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $assignmentModel = DeliveryAssignment::query()
            ->whereHas('agent', fn ($q) => $q->where('shipping_company_id', $supervisor->shipping_company_id)
                ->when($countryId, fn ($q2) => $q2->where('country_id', $countryId)))
            ->findOrFail($assignment);

        $newAgent = DeliveryAgent::findOrFail($request->input('new_delivery_agent_id'));

        try {
            $updated = $this->reassignmentService->reassign($assignmentModel, $newAgent, $supervisor);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }

        return ApiResponse::success(['assignment' => $this->transform($updated)], 'Assignment reassigned.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function scopedQuery(ShippingCompanySupervisor $supervisor): Builder
    {
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $agentIds = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->pluck('id');

        return DeliveryAssignment::whereIn('agent_id', $agentIds);
    }

    private function requirePermission(ShippingCompanySupervisor $supervisor, string $perm): void
    {
        abort_unless($supervisor->hasPermission($perm), 403, "You do not have the required permission: {$perm}.");
    }

    private function transform(DeliveryAssignment $a, bool $detailed = false): array
    {
        $base = [
            'id'               => $a->id,
            'status'           => $a->status?->value,
            'sub_order_number' => $a->subOrder?->sub_order_number,
            'agent'            => $a->agent ? ['id' => $a->agent->id, 'name' => $a->agent->name] : null,
            'assigned_at'      => $a->assigned_at?->toIso8601String(),
            'accepted_at'      => $a->accepted_at?->toIso8601String(),
            'picked_up_at'     => $a->picked_up_at?->toIso8601String(),
            'delivered_at'     => $a->delivered_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base['shipment'] = $a->shipment ? [
                'id'               => $a->shipment->id,
                'tracking_number'  => $a->shipment->tracking_number,
                'sub_order'        => $a->shipment->subOrder ? [
                    'id'                => $a->shipment->subOrder->id,
                    'sub_order_number'  => $a->shipment->subOrder->sub_order_number,
                ] : null,
                'tracking_events' => $a->shipment->trackingEvents?->map(fn ($e) => [
                    'status'      => $e->status?->value,
                    'occurred_at' => $e->occurred_at?->toIso8601String(),
                ]),
            ] : null;
            $base['cod_amount_collected'] = $a->cod_amount_collected;
            $base['delivery_otp']         = $a->delivery_otp;
            $base['agent_notes']          = $a->agent_notes;
        }

        return $base;
    }
}
