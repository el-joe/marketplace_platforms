<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Delivery\AssignmentListResource;
use App\Http\Responses\ApiResponse;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAssignment;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->load('zone.country', 'country');
        $today = today();

        $stats = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereDate('assigned_at', $today)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN (?,?) THEN 1 ELSE 0 END) as active
            ', [
                DeliveryAssignment::STATUS_DELIVERED,
                DeliveryAssignment::STATUS_FAILED,
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
            ->first();

        $earningsToday = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereDate('created_at', $today)
            ->sum('amount');

        $pending = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereIn('status', [
                DeliveryAssignment::STATUS_ASSIGNED,
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
            ->with(['subOrder.order', 'subOrder.items', 'shipment'])
            ->orderBy('assigned_at')
            ->limit(5)
            ->get();

        $zoneCities = [];
        if ($agent->zone && !empty($agent->zone->city_ids)) {
            $zoneCities = City::whereIn('id', $agent->zone->city_ids)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar'])
                ->toArray();
        }

        return ApiResponse::success([
            'stats' => [
                'total' => (int) $stats->total,
                'completed' => (int) $stats->completed,
                'failed' => (int) $stats->failed,
                'active' => (int) $stats->active,
            ],
            'earnings_today' => (int) $earningsToday,
            'currency' => $agent->country?->currency_code ?? $agent->zone?->country?->currency_code ?? 'AED',
            'is_available' => $agent->is_available,
            'status' => $agent->status,
            'pending_assignments' => AssignmentListResource::collection($pending),
            'zone' => $agent->zone ? [
                'id' => $agent->zone->id,
                'name' => $agent->zone->name,
                'code' => $agent->zone->code,
                'base_delivery_fee' => $agent->zone->base_delivery_fee,
                'cod_fee' => $agent->zone->cod_fee,
                'cities' => $zoneCities,
            ] : null,
        ]);
    }
}
