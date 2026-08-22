<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Enums\CarrierClaimStatus;
use App\Enums\DeliveryAgentCodSettlementStatus;
use App\Enums\DeliveryAgentEarningStatus;
use App\Enums\DeliveryAgentPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\CarrierClaim;
use App\Models\DeliveryAgent;
use App\Services\CarrierClaimService;
use App\Traits\HasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use HasExport;

    public function __construct(private readonly CarrierClaimService $claimService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Orders Report
    // ─────────────────────────────────────────────────────────────────────────

    public function orders(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $agentIds  = $this->scopedAgentIds($supervisor);
        $currency  = $supervisor->country?->currency_code
                   ?? $supervisor->company?->country?->currency_code
                   ?? '—';

        // ── Summary stats ──────────────────────────────────────────────────
        $statsBase = DB::table('delivery_assignments as da')
            ->join('sub_orders as so', 'so.id', '=', 'da.sub_order_id')
            ->join('orders as o',      'o.id',  '=', 'so.order_id')
            ->whereIn('da.agent_id', $agentIds);

        $stats = [
            'total'           => (clone $statsBase)->count(),
            'delivered'       => (clone $statsBase)->where('da.status', 'delivered')->count(),
            'failed'          => (clone $statsBase)->where('da.status', 'failed')->count(),
            'cod_unremitted'  => (clone $statsBase)
                ->where('so.cod_remittance_confirmed', false)
                ->whereNotNull('da.cod_amount_collected')
                ->sum('da.cod_amount_collected'),
            'shipping_revenue' => (clone $statsBase)
                ->where('da.status', 'delivered')
                ->sum('so.carrier_shipping_cost'),
        ];

        // ── Rows ──────────────────────────────────────────────────────────
        $query = $this->buildOrdersQuery($request, $agentIds);

        $rows = $query->orderByDesc('da.assigned_at')->paginate(25)->withQueryString();

        $agents = DeliveryAgent::whereIn('id', $agentIds)->orderBy('name')->get(['id', 'name']);

        return view('carrier.reports.orders', compact('stats', 'rows', 'agents', 'currency'));
    }

    public function ordersExport(Request $request): StreamedResponse
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $supervisor->country?->currency_code
                  ?? $supervisor->company?->country?->currency_code
                  ?? '';

        $rows = $this->buildOrdersQuery($request, $agentIds)
            ->orderByDesc('da.assigned_at')
            ->get();

        $headers = [
            'Sub-Order #', 'Order #', 'Agent', 'Status',
            'Assigned At', 'Delivered At',
            'Carrier Cost (' . $currency . ')', 'COD Collected (' . $currency . ')',
            'COD Remitted', 'Payment Method',
        ];

        $exportRows = $rows->map(fn ($r) => [
            $r->sub_order_number,
            $r->order_number,
            $r->agent_name,
            $r->status,
            $r->assigned_at,
            $r->delivered_at ?? '—',
            $r->carrier_shipping_cost,
            $r->cod_amount_collected ?? '—',
            $r->cod_remittance_confirmed ? 'Yes' : 'No',
            $r->payment_method,
        ]);

        return $this->exportExcel('orders-report', $headers, $exportRows);
    }

    private function buildOrdersQuery(Request $request, $agentIds)
    {
        $query = DB::table('delivery_assignments as da')
            ->join('delivery_agents as ag', 'ag.id', '=', 'da.agent_id')
            ->join('sub_orders as so',      'so.id', '=', 'da.sub_order_id')
            ->join('orders as o',           'o.id',  '=', 'so.order_id')
            ->whereIn('da.agent_id', $agentIds)
            ->select([
                'da.id as assignment_id',
                'da.status',
                'da.assigned_at',
                'da.delivered_at',
                'da.cod_amount_collected',
                'so.sub_order_number',
                'so.carrier_shipping_cost',
                'so.cod_remittance_confirmed',
                'o.order_number',
                'o.payment_method',
                'ag.name as agent_name',
            ]);

        if ($request->filled('agent_id')) {
            $query->where('da.agent_id', $request->input('agent_id'));
        }
        if ($request->filled('status')) {
            $query->where('da.status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('da.assigned_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('da.assigned_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Earnings Report
    // ─────────────────────────────────────────────────────────────────────────

    public function earnings(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $supervisor->country?->currency_code
                  ?? $supervisor->company?->country?->currency_code
                  ?? '—';

        $base = DB::table('delivery_agent_earnings')
            ->whereIn('agent_id', $agentIds)
            ->where('status', '!=', DeliveryAgentEarningStatus::Cancelled->value);

        $stats = [
            'total_gross' => (clone $base)->sum('amount'),
            'pending'     => (clone $base)->where('status', DeliveryAgentEarningStatus::Pending->value)->sum('amount'),
            'paid'        => (clone $base)->where('status', DeliveryAgentEarningStatus::Paid->value)->sum('amount'),
            'by_type'     => (clone $base)
                ->select('earning_type', DB::raw('SUM(amount) as total'))
                ->groupBy('earning_type')
                ->pluck('total', 'earning_type'),
        ];

        $agentSummary = DB::table('delivery_agent_earnings as e')
            ->join('delivery_agents as a', 'a.id', '=', 'e.agent_id')
            ->whereIn('e.agent_id', $agentIds)
            ->where('e.status', '!=', DeliveryAgentEarningStatus::Cancelled->value)
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('e.created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('e.created_at', '<=', $request->input('date_to')))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('e.agent_id', $request->input('agent_id')))
            ->select([
                'e.agent_id',
                'a.name as agent_name',
                DB::raw('SUM(CASE WHEN e.earning_type = "base_fee"     THEN e.amount ELSE 0 END) as base_fee'),
                DB::raw('SUM(CASE WHEN e.earning_type = "cod_handling" THEN e.amount ELSE 0 END) as cod_handling'),
                DB::raw('SUM(CASE WHEN e.earning_type = "bonus"        THEN e.amount ELSE 0 END) as bonus'),
                DB::raw('SUM(CASE WHEN e.earning_type = "tip"          THEN e.amount ELSE 0 END) as tip'),
                DB::raw('SUM(CASE WHEN e.earning_type = "deduction"    THEN e.amount ELSE 0 END) as deductions'),
                DB::raw('SUM(e.amount) as total'),
                DB::raw('COUNT(*) as entries'),
            ])
            ->groupBy('e.agent_id', 'a.name')
            ->orderByDesc('total')
            ->get();

        $agents = DeliveryAgent::whereIn('id', $agentIds)->orderBy('name')->get(['id', 'name']);

        return view('carrier.reports.earnings', compact('stats', 'agentSummary', 'agents', 'currency'));
    }

    public function earningsExport(Request $request): StreamedResponse
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $supervisor->country?->currency_code
                  ?? $supervisor->company?->country?->currency_code
                  ?? '';

        $rows = DB::table('delivery_agent_earnings as e')
            ->join('delivery_agents as a', 'a.id', '=', 'e.agent_id')
            ->whereIn('e.agent_id', $agentIds)
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('e.created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('e.created_at', '<=', $request->input('date_to')))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('e.agent_id', $request->input('agent_id')))
            ->select(['a.name as agent_name', 'e.earning_type', 'e.amount', 'e.currency', 'e.status', 'e.created_at'])
            ->orderByDesc('e.created_at')
            ->get();

        $headers = ['Agent', 'Type', 'Amount (' . $currency . ')', 'Currency', 'Status', 'Date'];

        $exportRows = $rows->map(fn ($r) => [
            $r->agent_name,
            $r->earning_type,
            $r->amount,
            $r->currency,
            $r->status,
            $r->created_at,
        ]);

        return $this->exportExcel('earnings-report', $headers, $exportRows);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payouts Report
    // ─────────────────────────────────────────────────────────────────────────

    public function payouts(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $supervisor->country?->currency_code
                  ?? $supervisor->company?->country?->currency_code
                  ?? '—';

        $payouts = DB::table('delivery_agent_payouts as p')
            ->join('delivery_agents as a', 'a.id', '=', 'p.agent_id')
            ->whereIn('p.agent_id', $agentIds)
            ->when($request->filled('agent_id'), fn ($q) => $q->where('p.agent_id', $request->input('agent_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('p.status', $request->input('status')))
            ->select([
                'p.id',
                'p.payout_number',
                'p.period_start',
                'p.period_end',
                'p.total_deliveries',
                'p.gross_earnings',
                'p.deductions',
                'p.net_amount',
                'p.currency',
                'p.status',
                'p.processed_at',
                'a.name as agent_name',
            ])
            ->orderByDesc('p.period_end')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total_net_paid' => DB::table('delivery_agent_payouts')
                ->whereIn('agent_id', $agentIds)
                ->where('status', DeliveryAgentPayoutStatus::Paid->value)
                ->sum('net_amount'),
            'pending_count' => DB::table('delivery_agent_payouts')
                ->whereIn('agent_id', $agentIds)
                ->where('status', DeliveryAgentPayoutStatus::Pending->value)
                ->count(),
        ];

        $agents = DeliveryAgent::whereIn('id', $agentIds)->orderBy('name')->get(['id', 'name']);

        return view('carrier.reports.payouts', compact('payouts', 'stats', 'agents', 'currency'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COD Settlements Report
    // ─────────────────────────────────────────────────────────────────────────

    public function codSettlements(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $supervisor->country?->currency_code
                  ?? $supervisor->company?->country?->currency_code
                  ?? '—';

        // ── Summary ───────────────────────────────────────────────────────────
        $stats = [
            'pending_cash'  => DB::table('delivery_agent_cod_settlements')
                ->whereIn('agent_id', $agentIds)
                ->where('status', DeliveryAgentCodSettlementStatus::Pending->value)
                ->sum('net_to_remit'),
            'settled_month' => DB::table('delivery_agent_cod_settlements')
                ->whereIn('agent_id', $agentIds)
                ->where('status', DeliveryAgentCodSettlementStatus::Settled->value)
                ->whereMonth('settled_at', now()->month)
                ->whereYear('settled_at', now()->year)
                ->sum('net_to_remit'),
            'disputed'      => DB::table('delivery_agent_cod_settlements')
                ->whereIn('agent_id', $agentIds)
                ->where('status', DeliveryAgentCodSettlementStatus::Disputed->value)
                ->count(),
            'discrepancies' => DB::table('delivery_agent_cod_settlements')
                ->whereIn('agent_id', $agentIds)
                ->where('has_collection_discrepancy', true)
                ->where('discrepancy_resolution', 'pending')
                ->count(),
        ];

        // ── Unsettled COD in agent custody (assignments not yet in any settlement) ──
        $agentPendingCod = DB::table('delivery_assignments')
            ->whereNotNull('cod_amount_collected')
            ->whereNull('cod_settlement_id')
            ->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, SUM(cod_amount_collected) as total')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        // ── Settlement rows ───────────────────────────────────────────────────
        $settlements = DB::table('delivery_agent_cod_settlements as s')
            ->join('delivery_agents as a', 'a.id', '=', 's.agent_id')
            ->whereIn('s.agent_id', $agentIds)
            ->when($request->filled('agent_id'), fn ($q) => $q->where('s.agent_id', $request->input('agent_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('s.status', $request->input('status')))
            ->select([
                's.id',
                's.period_start',
                's.period_end',
                's.total_cod_collected',
                's.total_earnings_owed',
                's.net_to_remit',
                's.status',
                's.settled_at',
                's.has_collection_discrepancy',
                's.discrepancy_amount',
                's.discrepancy_resolution',
                's.notes',
                'a.name as agent_name',
            ])
            ->orderByDesc('s.period_end')
            ->paginate(25)
            ->withQueryString();

        $agents = DeliveryAgent::whereIn('id', $agentIds)->orderBy('name')->get(['id', 'name']);

        return view('carrier.reports.cod-settlements', compact(
            'stats', 'settlements', 'agentPendingCod', 'agents', 'currency'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Performance Scorecard
    // ─────────────────────────────────────────────────────────────────────────

    public function performance(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $company   = $supervisor->company;
        $agentIds  = $this->scopedAgentIds($supervisor);
        $period    = $request->input('period', 'month');

        $scorecard = $this->claimService->getCarrierScorecard($company, $period);

        $agentRatings = DB::table('carrier_performance_ratings as r')
            ->join('delivery_agents as a', 'a.id', '=', 'r.delivery_agent_id')
            ->whereIn('r.delivery_agent_id', $agentIds)
            ->where('r.created_at', '>=', $this->periodSince($period))
            ->select([
                'r.delivery_agent_id',
                'a.name as agent_name',
                DB::raw('ROUND(AVG(r.rating), 2) as avg_rating'),
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN r.on_time = 1 THEN 1 ELSE 0 END) as on_time_count'),
                DB::raw('SUM(CASE WHEN r.on_time IS NOT NULL THEN 1 ELSE 0 END) as on_time_eligible'),
            ])
            ->groupBy('r.delivery_agent_id', 'a.name')
            ->orderByDesc('avg_rating')
            ->get();

        $recentRatings = DB::table('carrier_performance_ratings as r')
            ->join('sub_orders as so', 'so.id', '=', 'r.sub_order_id')
            ->leftJoin('delivery_agents as a', 'a.id', '=', 'r.delivery_agent_id')
            ->whereIn('r.delivery_agent_id', $agentIds)
            ->select([
                'r.rating',
                'r.on_time',
                'r.comment',
                'r.rated_by_type',
                'r.created_at',
                'so.sub_order_number',
                'a.name as agent_name',
            ])
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get();

        return view('carrier.reports.performance', compact(
            'scorecard', 'agentRatings', 'recentRatings', 'period', 'company'
        ));
    }

    public function performanceTrend(): \Illuminate\Http\JsonResponse
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $trend = $this->claimService->getRatingTrend($supervisor->company, 6);

        return response()->json($trend);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Carrier Claims (view-only)
    // ─────────────────────────────────────────────────────────────────────────

    public function claims(Request $request): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $companyId = $supervisor->shipping_company_id;
        $currency  = $supervisor->country?->currency_code
                   ?? $supervisor->company?->country?->currency_code
                   ?? '—';

        $stats = [
            'total'       => CarrierClaim::where('shipping_company_id', $companyId)->count(),
            'open'        => CarrierClaim::where('shipping_company_id', $companyId)
                                ->whereIn('status', [CarrierClaimStatus::Submitted, CarrierClaimStatus::UnderReview])
                                ->count(),
            'approved'    => CarrierClaim::where('shipping_company_id', $companyId)
                                ->whereIn('status', [CarrierClaimStatus::Approved, CarrierClaimStatus::Compensated])
                                ->count(),
            'compensated' => CarrierClaim::where('shipping_company_id', $companyId)
                                ->whereIn('status', [CarrierClaimStatus::Approved, CarrierClaimStatus::Compensated])
                                ->sum('compensated_amount'),
        ];

        $claims = CarrierClaim::where('shipping_company_id', $companyId)
            ->with(['deliveryAgent:id,name', 'shipment:id,tracking_number'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('claim_type'), fn ($q) => $q->where('claim_type', $request->input('claim_type')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('carrier.reports.claims', compact('stats', 'claims', 'currency'));
    }

    public function claimShow(string $claim): View
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('view_reports'), 403);

        $claim = CarrierClaim::where('shipping_company_id', $supervisor->shipping_company_id)
            ->with(['deliveryAgent:id,name', 'shipment:id,tracking_number,sub_order_id'])
            ->findOrFail($claim);

        $currency = $supervisor->country?->currency_code
                  ?? $supervisor->company?->country?->currency_code
                  ?? '—';

        return view('carrier.reports.claim-show', compact('claim', 'currency'));
    }

    private function periodSince(string $period): \Carbon\Carbon
    {
        return match ($period) {
            'week'    => now()->subWeek(),
            'quarter' => now()->subQuarter(),
            'year'    => now()->subYear(),
            default   => now()->subMonth(),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared helper — get agent IDs scoped to this supervisor's company+country
    // ─────────────────────────────────────────────────────────────────────────

    private function scopedAgentIds($supervisor): \Illuminate\Support\Collection
    {
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        return DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->pluck('id');
    }
}
