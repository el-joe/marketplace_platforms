<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryAgentCodSettlementStatus;
use App\Enums\DeliveryAgentEarningStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentCodSettlement;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAgentPayout;
use App\Models\DeliveryAssignment;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EarningsController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function index(Request $request): View|StreamedResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        if ($request->filled('export')) {
            return $this->exportEarnings($request, $agent);
        }

        $earnings = $this->buildEarningsQuery($request, $agent)
            ->with('assignment.subOrder')
            ->orderByDesc('created_at')
            ->paginate(20);

        $payouts = DeliveryAgentPayout::where('agent_id', $agent->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Settled period ranges — deliveries within these dates are already remitted
        $settledPeriods = DeliveryAgentCodSettlement::where('agent_id', $agent->id)
            ->where('status', DeliveryAgentCodSettlementStatus::Settled)
            ->get(['period_start', 'period_end']);

        // Cash physically held by the agent and not yet remitted
        $cashInHandCents = DeliveryAssignment::where('agent_id', $agent->id)
            ->where('status', DeliveryAssignment::STATUS_DELIVERED)
            ->whereNotNull('cod_amount_collected')
            ->where(function ($q) use ($settledPeriods) {
                foreach ($settledPeriods as $period) {
                    $q->where(function ($inner) use ($period) {
                        $inner->where('delivered_at', '<', $period->period_start)
                            ->orWhere('delivered_at', '>', $period->period_end);
                    });
                }
            })
            ->sum('cod_amount_collected');

        return view('delivery.earnings.index', compact('agent', 'earnings', 'payouts', 'cashInHandCents'));
    }

    /** Shared query for the index view and the export, scoped to the authenticated agent. */
    private function buildEarningsQuery(Request $request, DeliveryAgent $agent): Builder
    {
        $query = DeliveryAgentEarning::where('agent_id', $agent->id);

        return $this->applyFilters($query, $request, [
            'date_from' => fn ($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);
    }

    private function exportEarnings(Request $request, DeliveryAgent $agent): StreamedResponse
    {
        $earnings = $this->buildEarningsQuery($request, $agent)
            ->orderByDesc('created_at')
            ->get();

        $headers = ['Assignment', 'Amount', 'Currency', 'Date'];

        $rows = $earnings->map(fn (DeliveryAgentEarning $e) => [
            $e->delivery_assignment_id ?? '—',
            number_format($e->amount, 2),
            $e->currency,
            $e->created_at?->format('Y-m-d H:i') ?? '—',
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('delivery-earnings', $headers, $rows),
            'csv' => $this->exportCsv('delivery-earnings', $headers, $rows),
            'word' => $this->exportWord('delivery-earnings', 'Delivery Earnings', $rows),
            default => abort(400, __('delivery.messages.common.invalid_export_format')),
        };
    }

    public function summary(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        $thisMonth = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $pendingBalance = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->where('status', DeliveryAgentEarningStatus::Pending)
            ->sum('amount');

        $paidBalance = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->where('status', DeliveryAgentEarningStatus::Paid)
            ->sum('amount');

        $todayEarnings = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereDate('created_at', today())
            ->sum('amount');

        return response()->json([
            'today' => $todayEarnings,
            'month' => $thisMonth,
            'pending' => $pendingBalance,
            'paid' => $paidBalance,
        ]);
    }
}
