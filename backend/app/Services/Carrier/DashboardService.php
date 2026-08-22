<?php

namespace App\Services\Carrier;

use App\Enums\DeliveryAgentStatus;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\ShippingCompany;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function getSnapshot(ShippingCompany $company): array
    {
        return Cache::remember("carrier.dashboard.{$company->id}", 60, function () use ($company) {

            $agentIds = DeliveryAgent::where('shipping_company_id', $company->id)
                ->pluck('id');

            // Agent counts
            $agentsQuery = DeliveryAgent::where('shipping_company_id', $company->id);
            $agentsCount = [
                'total'     => (int) $agentsQuery->count(),
                'on_shift'  => (int) (clone $agentsQuery)->where('status', DeliveryAgentStatus::OnShift)->count(),
                'available' => (int) (clone $agentsQuery)->where('is_available', true)->count(),
            ];

            // Today's assignments scoped to this company's agents
            $todayRow = DeliveryAssignment::whereIn('agent_id', $agentIds)
                ->whereDate('assigned_at', today())
                ->selectRaw("
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = 'picked_up' THEN 1 ELSE 0 END) AS in_transit
                ")
                ->first();

            $todayAssignments = [
                'total'      => (int) ($todayRow->total      ?? 0),
                'completed'  => (int) ($todayRow->completed  ?? 0),
                'failed'     => (int) ($todayRow->failed     ?? 0),
                'in_transit' => (int) ($todayRow->in_transit ?? 0),
            ];

            // Recent 5 assignments
            $recentAssignments = DeliveryAssignment::whereIn('agent_id', $agentIds)
                ->with([
                    'agent:id,name',
                    'subOrder:id,sub_order_number',
                ])
                ->latest('assigned_at')
                ->limit(5)
                ->get()
                ->map(fn($a) => [
                    'id'               => $a->id,
                    'sub_order_number' => $a->subOrder?->sub_order_number,
                    'agent_name'       => $a->agent?->name,
                    'status'           => $a->status->value,
                    'assigned_at'      => $a->assigned_at?->toIso8601String(),
                ])
                ->all();

            $servedCities    = is_array($company->served_cities)    ? count($company->served_cities)    : 0;
            $servedCountries = is_array($company->served_countries)  ? count($company->served_countries) : 0;

            return [
                'agents_count'        => $agentsCount,
                'today_assignments'   => $todayAssignments,
                'company_status'      => $company->status->value,
                'served_area_summary' => [
                    'countries_count' => $servedCountries,
                    'cities_count'    => $servedCities,
                ],
                'recent_assignments'  => $recentAssignments,
            ];
        });
    }
}
