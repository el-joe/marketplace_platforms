<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAgentEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        $today = today();

        // Today's assignment stats
        $todayAssignments = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereDate('assigned_at', $today)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as active
            ', [
                DeliveryAssignment::STATUS_DELIVERED,
                DeliveryAssignment::STATUS_FAILED,
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
            ->first();

        // Today's earnings
        $earningsToday = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereDate('created_at', $today)
            ->sum('amount');

        // Pending assignments (require agent action)
        $pendingAssignments = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereIn('status', [
                DeliveryAssignment::STATUS_ASSIGNED,
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
            ->with(['subOrder.order', 'subOrder.items', 'shipment'])
            ->orderBy('assigned_at')
            ->limit(5)
            ->get();

        $agent->loadMissing('zone.country');

        $zoneCities = collect();
        if ($agent->zone && !empty($agent->zone->city_ids)) {
            $zoneCities = City::whereIn('id', $agent->zone->city_ids)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar']);
        }

        return view('delivery.dashboard', compact(
            'agent',
            'todayAssignments',
            'earningsToday',
            'pendingAssignments',
            'zoneCities'
        ));
    }
}
