<?php

namespace App\Services\Carrier;

use App\Enums\DeliveryAgentStatus;
use App\Enums\DeliveryAssignmentStatus;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\ShippingCompany;
use Illuminate\Support\Facades\DB;

class LiveMapService
{
    /**
     * Build a GeoJSON-style payload matching the admin live-map shape,
     * filtered strictly to this company's on_shift agents.
     */
    public function getCompanyMapData(ShippingCompany $company): array
    {
        $agents = DeliveryAgent::where('shipping_company_id', $company->id)
            ->where('status', DeliveryAgentStatus::OnShift)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->select(['id', 'name', 'status', 'is_available', 'current_latitude', 'current_longitude', 'last_location_at', 'zone_id'])
            ->get();

        // Batch-fetch which agents have an active delivery to avoid N+1
        $onDeliveryIds = DeliveryAssignment::whereIn('agent_id', $agents->pluck('id'))
            ->where('status', DeliveryAssignmentStatus::PickedUp)
            ->pluck('agent_id')
            ->flip(); // keyed for O(1) lookup

        $mappedAgents = $agents->map(function ($a) use ($onDeliveryIds) {
            $onDelivery = $onDeliveryIds->has($a->id);
            $color = $onDelivery ? 'blue' : ($a->is_available ? 'green' : 'gray');

            return [
                'type'      => 'agent',
                'id'        => $a->id,
                'name'      => $a->name,
                'status'    => $a->status->value,
                'available' => (bool) $a->is_available,
                'lat'       => (float) $a->current_latitude,
                'lng'       => (float) $a->current_longitude,
                'zone_id'   => $a->zone_id,
                'updated'   => $a->last_location_at?->diffForHumans() ?? 'Unknown',
                'color'     => $color,
            ];
        })->values()->all();

        // Active in-transit assignments for this company only
        $assignments = DB::table('delivery_assignments as da')
            ->join('delivery_agents as a', 'a.id', '=', 'da.agent_id')
            ->join('sub_orders as so', 'so.id', '=', 'da.sub_order_id')
            ->where('da.status', DeliveryAssignmentStatus::PickedUp->value)
            ->where('a.shipping_company_id', $company->id)
            ->whereNotNull('a.current_latitude')
            ->select([
                'da.id',
                'so.sub_order_number',
                'a.name as agent_name',
                'a.current_latitude as lat',
                'a.current_longitude as lng',
            ])
            ->get()
            ->map(fn($r) => [
                'type'             => 'assignment',
                'id'               => $r->id,
                'sub_order_number' => $r->sub_order_number,
                'agent_name'       => $r->agent_name,
                'lat'              => (float) $r->lat,
                'lng'              => (float) $r->lng,
            ])
            ->values()
            ->all();

        return [
            'agents'      => $mappedAgents,
            'assignments' => $assignments,
        ];
    }
}
