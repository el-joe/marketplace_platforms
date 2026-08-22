<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryAgentEarningStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentCodSettlement;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAgentEarning;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CodSettlementsController extends Controller
{
    public function index(): View
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        // Settled history (paginated)
        $settlements = DeliveryAgentCodSettlement::where('agent_id', $agent->id)
            ->orderByDesc('period_end')
            ->with(['assignments.subOrder.order'])
            ->paginate(10);

        // Current period: deliveries collected today that are not yet in a settlement
        $currentPeriodAssignments = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereNotNull('cod_amount_collected')
            ->whereNull('cod_settlement_id')
            ->whereDate('delivered_at', today())
            ->get();

        $currentCodTotal     = $currentPeriodAssignments->sum('cod_amount_collected');
        $currentEarningsTotal = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereIn('delivery_assignment_id', $currentPeriodAssignments->pluck('id'))
            ->where('status', '!=', DeliveryAgentEarningStatus::Cancelled)
            ->sum('amount');
        $currentNetOwed = max(0, $currentCodTotal - $currentEarningsTotal);

        return view('delivery.cod-settlements.index', compact(
            'agent',
            'settlements',
            'currentPeriodAssignments',
            'currentCodTotal',
            'currentEarningsTotal',
            'currentNetOwed',
        ));
    }
}
