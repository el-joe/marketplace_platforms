<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Enums\CarrierClaimStatus;
use App\Enums\DeliveryAgentCodSettlementStatus;
use App\Enums\DeliveryAgentEarningStatus;
use App\Enums\DeliveryAgentPayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CarrierClaim;
use App\Models\DeliveryAgent;
use App\Models\ShippingCompanySupervisor;
use App\Services\CarrierClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private readonly CarrierClaimService $claimService)
    {
    }

    public function orders(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $this->currency($supervisor);

        $statsBase = DB::table('delivery_assignments as da')
            ->join('sub_orders as so', 'so.id', '=', 'da.sub_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->whereIn('da.agent_id', $agentIds);

        $stats = [
            'total'            => (clone $statsBase)->count(),
            'delivered'        => (clone $statsBase)->where('da.status', 'delivered')->count(),
            'failed'           => (clone $statsBase)->where('da.status', 'failed')->count(),
            'cod_unremitted'   => (clone $statsBase)
                ->where('so.cod_remittance_confirmed', false)
                ->whereNotNull('da.cod_amount_collected')
                ->sum('da.cod_amount_collected'),
            'shipping_revenue' => (clone $statsBase)
                ->where('da.status', 'delivered')
                ->sum('so.carrier_shipping_cost'),
        ];

        $rows = $this->buildOrdersQuery($request, $agentIds)
            ->orderByDesc('da.assigned_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => [
                'stats'    => $stats,
                'currency' => $currency,
                'items'    => $rows->items(),
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page'    => $rows->lastPage(),
                    'per_page'     => $rows->perPage(),
                    'total'        => $rows->total(),
                ],
            ],
        ]);
    }

    private function buildOrdersQuery(Request $request, $agentIds)
    {
        $query = DB::table('delivery_assignments as da')
            ->join('delivery_agents as ag', 'ag.id', '=', 'da.agent_id')
            ->join('sub_orders as so', 'so.id', '=', 'da.sub_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
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

    public function earnings(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $this->currency($supervisor);

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

        return ApiResponse::success([
            'stats'         => $stats,
            'agent_summary' => $agentSummary,
            'currency'      => $currency,
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $this->currency($supervisor);

        $payouts = DB::table('delivery_agent_payouts as p')
            ->join('delivery_agents as a', 'a.id', '=', 'p.agent_id')
            ->whereIn('p.agent_id', $agentIds)
            ->when($request->filled('agent_id'), fn ($q) => $q->where('p.agent_id', $request->input('agent_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('p.status', $request->input('status')))
            ->select([
                'p.id', 'p.payout_number', 'p.period_start', 'p.period_end', 'p.total_deliveries',
                'p.gross_earnings', 'p.deductions', 'p.net_amount', 'p.currency', 'p.status',
                'p.processed_at', 'a.name as agent_name',
            ])
            ->orderByDesc('p.period_end')
            ->paginate($request->integer('per_page', 25));

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

        return response()->json([
            'success' => true,
            'data' => [
                'stats'    => $stats,
                'currency' => $currency,
                'items'    => $payouts->items(),
                'meta' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page'    => $payouts->lastPage(),
                    'per_page'     => $payouts->perPage(),
                    'total'        => $payouts->total(),
                ],
            ],
        ]);
    }

    public function codSettlements(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $agentIds = $this->scopedAgentIds($supervisor);
        $currency = $this->currency($supervisor);

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

        $agentPendingCod = DB::table('delivery_assignments')
            ->whereNotNull('cod_amount_collected')
            ->whereNull('cod_settlement_id')
            ->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, SUM(cod_amount_collected) as total')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        $settlements = DB::table('delivery_agent_cod_settlements as s')
            ->join('delivery_agents as a', 'a.id', '=', 's.agent_id')
            ->whereIn('s.agent_id', $agentIds)
            ->when($request->filled('agent_id'), fn ($q) => $q->where('s.agent_id', $request->input('agent_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('s.status', $request->input('status')))
            ->select([
                's.id', 's.period_start', 's.period_end', 's.total_cod_collected', 's.total_earnings_owed',
                's.net_to_remit', 's.status', 's.settled_at', 's.has_collection_discrepancy',
                's.discrepancy_amount', 's.discrepancy_resolution', 's.notes', 'a.name as agent_name',
            ])
            ->orderByDesc('s.period_end')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => [
                'stats'              => $stats,
                'agent_pending_cod'  => $agentPendingCod,
                'currency'           => $currency,
                'items'              => $settlements->items(),
                'meta' => [
                    'current_page' => $settlements->currentPage(),
                    'last_page'    => $settlements->lastPage(),
                    'per_page'     => $settlements->perPage(),
                    'total'        => $settlements->total(),
                ],
            ],
        ]);
    }

    public function performance(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $company  = $supervisor->company;
        $agentIds = $this->scopedAgentIds($supervisor);
        $period   = $request->input('period', 'month');

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
                'r.rating', 'r.on_time', 'r.comment', 'r.rated_by_type', 'r.created_at',
                'so.sub_order_number', 'a.name as agent_name',
            ])
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get();

        return ApiResponse::success([
            'scorecard'      => $scorecard,
            'agent_ratings'  => $agentRatings,
            'recent_ratings' => $recentRatings,
            'period'         => $period,
        ]);
    }

    public function performanceTrend(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $trend = $this->claimService->getRatingTrend($supervisor->company, 6);

        return ApiResponse::success($trend);
    }

    public function claims(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $companyId = $supervisor->shipping_company_id;
        $currency  = $this->currency($supervisor);

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
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => [
                'stats'    => $stats,
                'currency' => $currency,
                'items'    => collect($claims->items())->map(fn (CarrierClaim $c) => $this->transformClaim($c)),
                'meta' => [
                    'current_page' => $claims->currentPage(),
                    'last_page'    => $claims->lastPage(),
                    'per_page'     => $claims->perPage(),
                    'total'        => $claims->total(),
                ],
            ],
        ]);
    }

    public function claimShow(string $claim): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requirePermission($supervisor, 'view_reports');

        $model = CarrierClaim::where('shipping_company_id', $supervisor->shipping_company_id)
            ->with(['deliveryAgent:id,name', 'shipment:id,tracking_number,sub_order_id'])
            ->findOrFail($claim);

        return ApiResponse::success([
            'claim'    => $this->transformClaim($model, detailed: true),
            'currency' => $this->currency($supervisor),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function requirePermission(ShippingCompanySupervisor $supervisor, string $perm): void
    {
        abort_unless($supervisor->hasPermission($perm), 403, "You do not have the required permission: {$perm}.");
    }

    private function currency(ShippingCompanySupervisor $supervisor): string
    {
        return $supervisor->country?->currency_code
            ?? $supervisor->company?->country?->currency_code
            ?? '—';
    }

    private function scopedAgentIds(ShippingCompanySupervisor $supervisor): \Illuminate\Support\Collection
    {
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        return DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->pluck('id');
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

    private function transformClaim(CarrierClaim $c, bool $detailed = false): array
    {
        $base = [
            'id'                  => $c->id,
            'claim_number'        => $c->claim_number,
            'claim_type'          => $c->claim_type?->value,
            'status'              => $c->status?->value,
            'claimed_amount'      => $c->claimed_amount,
            'compensated_amount'  => $c->compensated_amount,
            'delivery_agent'      => $c->deliveryAgent ? ['id' => $c->deliveryAgent->id, 'name' => $c->deliveryAgent->name] : null,
            'shipment'            => $c->shipment ? ['id' => $c->shipment->id, 'tracking_number' => $c->shipment->tracking_number] : null,
            'created_at'          => $c->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base['description']       = $c->description;
            $base['evidence_files']    = $c->evidence_files;
            $base['resolution_notes']  = $c->resolution_notes;
            $base['resolved_at']       = $c->resolved_at?->toIso8601String();
        }

        return $base;
    }
}
