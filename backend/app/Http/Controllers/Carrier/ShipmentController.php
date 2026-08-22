<?php

namespace App\Http\Controllers\Carrier;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentController extends Controller
{
    // ── GET /shipments/unassigned ───────────────────────────────────────────
    // Permission: view_orders. Scoped to the carriers linked to this
    // supervisor's company via shipping_carriers.shipping_company_id.

    public function unassigned(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');

        if ($carrierIds->isEmpty()) {
            return ApiResponse::success([
                'shipments'          => [],
                'no_carriers_linked' => true,
            ], __('carrier.assignments.no_carriers_linked_body'));
        }

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

        $shipments = Shipment::whereDoesntHave('deliveryAssignment')
            ->where('status', '!=', ShipmentStatus::Delivered)
            ->whereIn('carrier_id', $carrierIds)
            ->when($countryId, fn ($q) =>
                $q->whereHas('subOrder.order', fn ($q2) =>
                    $q2->where('country_id', $countryId)
                )
            )
            ->when($cityIds->isNotEmpty(), fn ($q) =>
                $q->whereHas('subOrder.order', fn ($q2) =>
                    $q2->whereIn(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(shipping_address_snapshot, '$.city_id'))"),
                        $cityIds
                    )
                )
            )
            ->with([
                'subOrder:id,sub_order_number,status',
                'subOrder.order:id,order_number,customer_id',
                'subOrder.order.customer:id,name,phone',
            ])
            ->latest()
            ->paginate(20);

        return ApiResponse::success($shipments->through(fn (Shipment $s) => [
            'id'               => $s->id,
            'tracking_number'  => $s->tracking_number,
            'status'           => $s->status?->value,
            'sub_order_number' => $s->subOrder?->sub_order_number,
            'order_number'     => $s->subOrder?->order?->order_number,
            'customer_name'    => $s->subOrder?->order?->customer?->name,
            'customer_phone'   => $s->subOrder?->order?->customer?->phone,
            'created_at'       => $s->created_at?->toIso8601String(),
        ]));
    }

    // ── GET /shipments/{id} ──────────────────────────────────────────────────
    // Permission: view_orders. 404s (not 403) if the shipment's carrier is
    // outside the supervisor's company, to avoid leaking existence.

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');

        // NOTE: show() does not filter by supervisor's country — it 404s only on
        // carrier scope, matching the pre-existing behavior for this endpoint.
        $shipment = Shipment::whereIn('carrier_id', $carrierIds)
            ->with(['subOrder.order.customer', 'subOrder.items', 'trackingEvents', 'deliveryAssignment.agent'])
            ->findOrFail($id);

        return ApiResponse::success([
            'id'                  => $shipment->id,
            'tracking_number'     => $shipment->tracking_number,
            'status'              => $shipment->status?->value,
            'sub_order_number'    => $shipment->subOrder?->sub_order_number,
            'order_number'        => $shipment->subOrder?->order?->order_number,
            'customer_name'       => $shipment->subOrder?->order?->customer?->name,
            'customer_phone'      => $shipment->subOrder?->order?->customer?->phone,
            'delivery_assignment' => $shipment->deliveryAssignment ? [
                'agent_name' => $shipment->deliveryAssignment->agent?->name,
                'status'     => $shipment->deliveryAssignment->status?->value,
            ] : null,
            'tracking_events' => $shipment->trackingEvents->map(fn ($e) => [
                'status'      => $e->status,
                'description' => $e->description,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ]),
        ]);
    }

    // ── POST /shipments/{id}/assign-agent ────────────────────────────────────
    // Permission: assign_agents. Creates the initial DeliveryAssignment for a
    // shipment that has none yet; scoped to the supervisor's company carriers
    // and agents, same as the web portal's AssignmentController::assign().

    public function assignAgent(Request $request, string $shipmentId): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $request->validate(['agent_id' => ['required', 'string', 'exists:delivery_agents,id']]);

        $carrierIds = ShippingCarrier::where('shipping_company_id', $supervisor->shipping_company_id)->pluck('id');
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $shipment = Shipment::whereDoesntHave('deliveryAssignment')
            ->whereIn('carrier_id', $carrierIds)
            ->findOrFail($shipmentId);

        $agent = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->where('id', $request->agent_id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($countryId && $agent->country_id !== $countryId) {
            return ApiResponse::error('Agent does not operate in this country.', [], 422);
        }

        $assignment = DB::transaction(function () use ($shipment, $agent) {
            return DeliveryAssignment::create([
                'shipment_id'  => $shipment->id,
                'sub_order_id' => $shipment->sub_order_id,
                'agent_id'     => $agent->id,
                'status'       => 'assigned',
                'assigned_at'  => now(),
                'delivery_otp' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            ]);
        });

        $agent->notify(new NewDeliveryAssigned($assignment));

        return ApiResponse::success([
            'assignment_id' => $assignment->id,
            'agent_name'    => $agent->name,
        ], __('carrier.api.assignment_created'));
    }
}
