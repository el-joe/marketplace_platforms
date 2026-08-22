<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        $company    = $supervisor->company;

        // Compat shim: falls back to the company's home country until the
        // supervisor is backfilled with their own country_id. Kept in sync
        // with the same scoping used by AssignmentController/AgentController
        // so the dashboard's counts match what those pages actually list.
        $countryId = $supervisor->country_id ?? $company->country_id;

        $agentsQuery = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId));

        $agentIds = (clone $agentsQuery)->pluck('id');

        $stats = [
            'total_agents'     => $agentIds->count(),
            'active_agents'    => (clone $agentsQuery)->where('status', 'active')->count(),
            'pending'          => DeliveryAssignment::whereIn('agent_id', $agentIds)
                                    ->where('status', 'assigned')->count(),
            'in_progress'      => DeliveryAssignment::whereIn('agent_id', $agentIds)
                                    ->whereIn('status', ['accepted', 'picked_up'])->count(),
            'delivered_today'  => DeliveryAssignment::whereIn('agent_id', $agentIds)
                                    ->where('status', 'delivered')
                                    ->whereDate('delivered_at', today())->count(),
        ];

        $recentAssignments = DeliveryAssignment::whereIn('agent_id', $agentIds)
            ->with('agent')
            ->latest('assigned_at')
            ->limit(10)
            ->get();

        return view('carrier.dashboard', compact('company', 'stats', 'recentAssignments'));
    }
}
