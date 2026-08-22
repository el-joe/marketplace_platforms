<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryAgentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryZone;
use App\Models\Shipment;
use App\Models\SubOrder;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeliveryAssignmentController extends Controller
{
    use HasDataTable;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $zones = DeliveryZone::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $agents = DeliveryAgent::whereIn('status', [DeliveryAgentStatus::Active, DeliveryAgentStatus::OnShift])
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return view('admin.delivery.assignments.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Delivery'],
                ['label' => 'Assignments'],
            ],
            'zones' => $zones,
            'agents' => $agents,
        ]);
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['sub_orders.sub_order_number'], 'orderable_column' => 'sub_orders.sub_order_number'],
            ['searchable_columns' => ['delivery_agents.name'], 'orderable_column' => 'delivery_agents.name'],
            ['searchable_columns' => ['delivery_zones.name'], 'orderable_column' => 'delivery_zones.name'],
            ['orderable_column' => 'delivery_assignments.status'],
            ['orderable_column' => 'delivery_assignments.assigned_at'],
            ['orderable_column' => 'delivery_assignments.delivered_at'],
            [],
        ];

        $query = DB::table('delivery_assignments')
            ->leftJoin('delivery_agents', 'delivery_agents.id', '=', 'delivery_assignments.agent_id')
            ->leftJoin('delivery_zones', 'delivery_zones.id', '=', 'delivery_agents.zone_id')
            ->leftJoin('sub_orders', 'sub_orders.id', '=', 'delivery_assignments.sub_order_id')
            ->select([
                'delivery_assignments.id',
                'delivery_assignments.status',
                'delivery_assignments.assigned_at',
                'delivery_assignments.picked_up_at',
                'delivery_assignments.delivered_at',
                'delivery_assignments.failed_at',
                'delivery_assignments.failure_reason',
                'delivery_assignments.customer_rating',
                'delivery_agents.id as agent_id',
                'delivery_agents.name as agent_name',
                'delivery_zones.name as zone_name',
                'sub_orders.sub_order_number',
            ]);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('delivery_assignments.status', $v),
            'agent_id' => fn($q, $v) => $q->where('delivery_assignments.agent_id', $v),
            'zone_id' => fn($q, $v) => $q->where('delivery_agents.zone_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('delivery_assignments.assigned_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('delivery_assignments.assigned_at', '<=', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $duration = null;
            if ($row->assigned_at && $row->delivered_at) {
                $duration = (int) \Carbon\Carbon::parse($row->assigned_at)
                    ->diffInMinutes(\Carbon\Carbon::parse($row->delivered_at));
            }

            return [
                'id' => $row->id,
                'sub_order_number' => $row->sub_order_number ?? '—',
                'agent_name' => e($row->agent_name ?? '—'),
                'agent_id' => $row->agent_id,
                'zone_name' => e($row->zone_name ?? '—'),
                'status' => $row->status,
                'assigned_at' => $row->assigned_at
                    ? \Carbon\Carbon::parse($row->assigned_at)->format('d M Y H:i') : '—',
                'delivered_at' => $row->delivered_at
                    ? \Carbon\Carbon::parse($row->delivered_at)->format('d M Y H:i') : '—',
                'duration_minutes' => $duration,
                'customer_rating' => $row->customer_rating,
                'failure_reason' => $row->failure_reason,
            ];
        });
    }

    // ── Auto-Assign ───────────────────────────────────────────────────────────

    /**
     * Find the nearest available agent to the pickup location, create assignment.
     */
    public function autoAssign(Request $request): JsonResponse
    {
        $request->validate([
            'sub_order_id' => ['required', 'exists:sub_orders,id'],
            'shipment_id' => ['required', 'exists:shipments,id'],
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Guard: no existing active assignment
        $exists = DeliveryAssignment::where('sub_order_id', $request->sub_order_id)
            ->whereNotIn('status', [DeliveryAssignment::STATUS_DELIVERED, DeliveryAssignment::STATUS_FAILED])
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'An active assignment already exists for this order.'], 422);
        }

        $subOrder = SubOrder::with('order')->findOrFail($request->sub_order_id);
        $order    = $subOrder->order;

        $lat = (float) $request->pickup_latitude;
        $lng = (float) $request->pickup_longitude;

        // Extract delivery city_id from shipping address snapshot
        $deliveryCityId = data_get($order->shipping_address_snapshot, 'city_id');

        // ── Pass 1: Zone-matched agents ────────────────────────────────────────
        $agent = null;

        if ($deliveryCityId) {
            $zoneIds = DeliveryZone::where('is_active', true)
                ->where('country_id', $order->country_id)
                ->coversCity($deliveryCityId)
                ->pluck('id');

            if ($zoneIds->isNotEmpty()) {
                $agent = DeliveryAgent::where('status', DeliveryAgentStatus::Active)
                    ->where('is_available', true)
                    ->where('country_id', $order->country_id)
                    ->whereIn('zone_id', $zoneIds)
                    ->whereNotNull('current_latitude')
                    ->whereNotNull('current_longitude')
                    ->selectRaw("*, (6371 * ACOS(
                        COS(RADIANS(?)) * COS(RADIANS(current_latitude)) *
                        COS(RADIANS(current_longitude) - RADIANS(?)) +
                        SIN(RADIANS(?)) * SIN(RADIANS(current_latitude))
                    )) AS distance_km", [$lat, $lng, $lat])
                    ->orderBy('distance_km')
                    ->first();
            }
        }

        // ── Pass 2: Country-wide fallback (existing behavior) ──────────────────
        $usedFallback = false;
        if (!$agent) {
            $usedFallback = true;
            $agent = DeliveryAgent::where('status', DeliveryAgentStatus::Active)
                ->where('is_available', true)
                ->where('country_id', $order->country_id)
                ->whereNotNull('current_latitude')
                ->whereNotNull('current_longitude')
                ->selectRaw("*, (6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(current_latitude)) *
                    COS(RADIANS(current_longitude) - RADIANS(?)) +
                    SIN(RADIANS(?)) * SIN(RADIANS(current_latitude))
                )) AS distance_km", [$lat, $lng, $lat])
                ->orderBy('distance_km')
                ->first();
        }

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'No available agents found for this order\'s country.'], 422);
        }

        $assignment = DeliveryAssignment::create([
            'sub_order_id' => $request->sub_order_id,
            'shipment_id' => $request->shipment_id,
            'agent_id' => $agent->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'delivery_otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        ]);

        return response()->json([
            'success' => true,
            'message' => ($usedFallback ? '[Fallback] ' : '') . "Assigned to {$agent->name} ({$agent->distance_km} km away).",
            'assignment' => [
                'id' => $assignment->id,
                'agent_name' => $agent->name,
                'agent_id' => $agent->id,
                'distance_km' => round($agent->distance_km, 2),
                'zone_matched' => !$usedFallback,
            ],
        ]);
    }

    // ── Manual Assign ─────────────────────────────────────────────────────────

    public function manualAssign(Request $request): JsonResponse
    {
        $request->validate([
            'sub_order_id' => ['required', 'exists:sub_orders,id'],
            'shipment_id' => ['required', 'exists:shipments,id'],
            'agent_id' => ['required', 'exists:delivery_agents,id'],
        ]);

        $subOrder = SubOrder::with('order')->findOrFail($request->sub_order_id);

        $agent = DeliveryAgent::where('id', $request->agent_id)
            ->where('country_id', $subOrder->order->country_id)
            ->firstOrFail();

        $assignment = DeliveryAssignment::create([
            'sub_order_id' => $request->sub_order_id,
            'shipment_id' => $request->shipment_id,
            'agent_id' => $agent->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'delivery_otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Manually assigned to {$agent->name}.",
            'assignment' => ['id' => $assignment->id],
        ]);
    }

    // ── Live Map ──────────────────────────────────────────────────────────────

    public function liveMap(): JsonResponse
    {
        // All on_shift agents with coordinates
        $agents = DeliveryAgent::where('status', DeliveryAgentStatus::OnShift)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->select(['id', 'name', 'status', 'is_available', 'current_latitude', 'current_longitude', 'last_location_at', 'zone_id'])
            ->with('zone:id,name')
            ->get()
            ->map(fn($a) => [
                'type' => 'agent',
                'id' => $a->id,
                'name' => $a->name,
                'status' => $a->status->value,
                'available' => (bool) $a->is_available,
                'lat' => (float) $a->current_latitude,
                'lng' => (float) $a->current_longitude,
                'zone_id' => $a->zone_id,
                'zone_name' => $a->zone?->name ?? null,
                'updated' => $a->last_location_at?->diffForHumans() ?? 'Unknown',
            ]);

        // Active assignments (in_transit = picked_up, not yet delivered)
        $assignments = DB::table('delivery_assignments as da')
            ->join('delivery_agents as a', 'a.id', '=', 'da.agent_id')
            ->join('sub_orders as so', 'so.id', '=', 'da.sub_order_id')
            ->where('da.status', 'picked_up')
            ->whereNotNull('a.current_latitude')
            ->select([
                'da.id',
                'so.sub_order_number',
                'a.name as agent_name',
                'a.current_latitude as lat',
                'a.current_longitude as lng',
                'da.assigned_at',
            ])
            ->get()
            ->map(fn($r) => [
                'type' => 'assignment',
                'id' => $r->id,
                'sub_order_number' => $r->sub_order_number,
                'agent_name' => $r->agent_name,
                'lat' => (float) $r->lat,
                'lng' => (float) $r->lng,
            ]);

        return response()->json([
            'agents' => $agents,
            'assignments' => $assignments,
        ]);
    }

    // ── Select2 search endpoints ─────────────────────────────────────────────

    public function searchSubOrders(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $subOrders = SubOrder::query()
            ->with('order:id,order_number')
            ->when($term, fn ($q) => $q->where(function ($q) use ($term) {
                $q->where('sub_order_number', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$term}%"));
            }))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($so) => [
                'id' => $so->id,
                'text' => $so->sub_order_number
                    . ' (Order: ' . ($so->order->order_number ?? '—') . ')'
                    . ' [' . $so->status->value . ']',
            ]);

        return response()->json($subOrders);
    }

    public function searchShipments(Request $request): JsonResponse
    {
        $subOrderId = $request->input('sub_order_id');
        $term = $request->input('q', '');

        if (!$subOrderId) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
        }

        $shipments = Shipment::query()
            ->where('sub_order_id', $subOrderId)
            ->when($term, fn ($q) => $q->where('tracking_number', 'like', "%{$term}%"))
            ->whereDoesntHave('deliveryAssignment', fn ($q) => $q->whereNotIn('status', [DeliveryAssignment::STATUS_FAILED]))
            ->paginate(20)
            ->through(fn ($s) => [
                'id' => $s->id,
                'text' => $s->tracking_number . ' [' . $s->status->value . ']',
            ]);

        return response()->json($shipments);
    }
}
