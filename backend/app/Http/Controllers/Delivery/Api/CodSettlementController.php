<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Enums\DeliveryAgentEarningStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentCodSettlement;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAssignment;
use Illuminate\Http\JsonResponse;

class CodSettlementController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $settlements = DeliveryAgentCodSettlement::where('agent_id', $agent->id)
            ->orderByDesc('period_end')
            ->with('assignments.subOrder.order')
            ->paginate(10);

        return ApiResponse::success([
            'items' => $settlements->getCollection()->map(fn ($s) => [
                'id' => $s->id,
                'status' => $s->status,
                'period_start' => $s->period_start?->toDateString(),
                'period_end' => $s->period_end?->toDateString(),
                'total_cod_collected' => $s->total_cod_collected,
                'total_earnings_owed' => $s->total_earnings_owed,
                'net_to_remit' => $s->net_to_remit,
                'settled_at' => $s->settled_at?->toIso8601String(),
                'assignments' => $s->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'sub_order_number' => $a->subOrder?->sub_order_number,
                    'delivered_at' => $a->delivered_at?->toIso8601String(),
                    'cod_collected' => $a->cod_amount_collected,
                ]),
            ]),
            'meta' => [
                'current_page' => $settlements->currentPage(),
                'last_page' => $settlements->lastPage(),
            ],
        ]);
    }

    /** Today's open COD, not yet in a settlement. */
    public function current(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->load('country', 'zone.country');

        $assignments = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereNotNull('cod_amount_collected')
            ->whereNull('cod_settlement_id')
            ->whereDate('delivered_at', today())
            ->get();

        $codTotal = $assignments->sum('cod_amount_collected');
        $earningsTotal = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereIn('delivery_assignment_id', $assignments->pluck('id'))
            ->where('status', '!=', DeliveryAgentEarningStatus::Cancelled)
            ->sum('amount');
        $netOwed = max(0, $codTotal - $earningsTotal);

        return ApiResponse::success([
            'currency' => $agent->country?->currency_code ?? $agent->zone?->country?->currency_code ?? 'AED',
            'cod_total' => (int) $codTotal,
            'earnings_total' => (int) $earningsTotal,
            'net_to_remit' => (int) $netOwed,
            'deliveries' => $assignments->map(fn ($a) => [
                'id' => $a->id,
                'sub_order_number' => $a->subOrder?->sub_order_number,
                'delivered_at' => $a->delivered_at?->toIso8601String(),
                'cod_collected' => $a->cod_amount_collected,
            ]),
        ]);
    }
}
