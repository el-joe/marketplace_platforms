<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\CodSettlementDiscrepancyResolution;
use App\Enums\DeliveryAgentCodSettlementStatus;
use App\Enums\DeliveryAgentEarningStatus;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentCodSettlement;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAssignment;
use App\Models\SubOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CodSettlementController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $pendingCashCents = DeliveryAgentCodSettlement::where('status', DeliveryAgentCodSettlementStatus::Pending)
            ->sum('net_to_remit');

        $settledThisMonthCents = DeliveryAgentCodSettlement::where('status', DeliveryAgentCodSettlementStatus::Settled)
            ->whereMonth('settled_at', now()->month)
            ->whereYear('settled_at', now()->year)
            ->sum('net_to_remit');

        $disputedCount = DeliveryAgentCodSettlement::where('status', DeliveryAgentCodSettlementStatus::Disputed)->count();

        $discrepancyCount = DeliveryAgentCodSettlement::where('has_collection_discrepancy', true)
            ->where('discrepancy_resolution', CodSettlementDiscrepancyResolution::Pending)
            ->count();

        // Per-agent summary: pending cash in custody + last settlement
        $agents = DeliveryAgent::query()
            ->withCount(['codSettlements as pending_count' => fn($q) => $q->where('status', DeliveryAgentCodSettlementStatus::Pending)])
            ->with(['codSettlements' => fn($q) => $q->orderByDesc('period_end')->limit(1)])
            ->whereHas('assignments', fn($q) => $q->whereNotNull('cod_amount_collected'))
            ->orWhereHas('codSettlements')
            ->get();

        // Pending COD in each agent's custody (assignments not yet in any settlement)
        $agentPendingCod = DeliveryAssignment::query()
            ->whereNotNull('cod_amount_collected')
            ->whereNull('cod_settlement_id')
            ->selectRaw('agent_id, SUM(cod_amount_collected) as total')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        // Settlements with unresolved collection discrepancies (admin review queue).
        $flaggedSettlements = null;
        if ($request->boolean('discrepancies')) {
            $flaggedSettlements = DeliveryAgentCodSettlement::with('agent')
                ->where('has_collection_discrepancy', true)
                ->where('discrepancy_resolution', CodSettlementDiscrepancyResolution::Pending)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('admin.delivery.cod-settlements.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.delivery')],
                ['label' => __('admin.nav.cod_settlements')],
            ],
            'pendingCashCents'        => $pendingCashCents,
            'settledThisMonthCents'   => $settledThisMonthCents,
            'disputedCount'           => $disputedCount,
            'discrepancyCount'        => $discrepancyCount,
            'agents'                  => $agents,
            'agentPendingCod'         => $agentPendingCod,
            'flaggedSettlements'      => $flaggedSettlements,
            'showingDiscrepancies'    => $request->boolean('discrepancies'),
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(DeliveryAgentCodSettlement $settlement): View
    {
        $settlement->load('agent');

        // Assignments tied to this settlement
        $assignments = DeliveryAssignment::with('subOrder.order')
            ->where('cod_settlement_id', $settlement->id)
            ->get();

        // Earnings in the settlement's period for this agent
        $earnings = DeliveryAgentEarning::where('agent_id', $settlement->agent_id)
            ->whereBetween('created_at', [$settlement->period_start, $settlement->period_end])
            ->where('status', '!=', DeliveryAgentEarningStatus::Cancelled)
            ->get();

        // Settlement history for this agent
        $history = DeliveryAgentCodSettlement::where('agent_id', $settlement->agent_id)
            ->orderByDesc('period_end')
            ->get();

        // Unsettled assignments for the agent (not yet in any settlement)
        $openAssignments = DeliveryAssignment::with('subOrder.order')
            ->where('agent_id', $settlement->agent_id)
            ->whereNull('cod_settlement_id')
            ->whereNotNull('cod_amount_collected')
            ->get();

        return view('admin.delivery.cod-settlements.show', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.delivery')],
                ['label' => __('admin.nav.cod_settlements'), 'url' => route('admin.delivery.cod-settlements.index')],
                ['label' => $settlement->agent->name ?? __('admin.cod_settlements_section.settlement_label')],
            ],
            'settlement'       => $settlement,
            'assignments'      => $assignments,
            'earnings'         => $earnings,
            'history'          => $history,
            'openAssignments'  => $openAssignments,
        ]);
    }

    // ── Generate ──────────────────────────────────────────────────────────────

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id'     => ['required', 'uuid', 'exists:delivery_agents,id'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $agent       = DeliveryAgent::findOrFail($validated['agent_id']);
        $periodStart = $validated['period_start'];
        $periodEnd   = $validated['period_end'];

        // Verify no overlapping settlement exists for this agent
        $overlap = DeliveryAgentCodSettlement::where('agent_id', $agent->id)
            ->where('period_start', '<=', $periodEnd)
            ->where('period_end', '>=', $periodStart)
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => __('admin.cod_settlements_section.overlap_exists'),
            ], 422);
        }

        // Collect assignments with COD collected and not yet settled
        $assignments = DeliveryAssignment::with('subOrder.order')
            ->where('agent_id', $agent->id)
            ->whereBetween('delivered_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
            ->whereNotNull('cod_amount_collected')
            ->whereNull('cod_settlement_id')
            ->get();

        $collected = $assignments->sum('cod_amount_collected');

        if ($collected === 0) {
            return response()->json([
                'success' => false,
                'message' => __('admin.cod_settlements_section.no_unsettled_deliveries'),
            ], 422);
        }

        $earningsOwed = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereIn('earning_type', ['base_fee', 'cod_handling'])
            ->whereBetween('created_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
            ->where('status', '!=', DeliveryAgentEarningStatus::Cancelled)
            ->sum('amount');

        $netToRemit = $collected - $earningsOwed;

        if ($netToRemit < 0) {
            return response()->json([
                'success' => false,
                'message' => __('admin.cod_settlements_section.negative_net_amount'),
            ], 422);
        }

        // Aggregate per-assignment discrepancies before the transaction.
        $discrepancyAssignments = $assignments->filter(fn($a) => ! empty($a->discrepancy_note));
        $hasDiscrepancy         = $discrepancyAssignments->isNotEmpty();
        $discrepancyNotes       = $discrepancyAssignments
            ->map(fn($a) => "Assignment {$a->id}: {$a->discrepancy_note}")
            ->implode("\n");
        $discrepancyAmountCents = $discrepancyAssignments->sum(function ($a) {
            $expected = (int) ($a->subOrder?->order?->total ?? 0);
            return max(0, $expected - (int) $a->cod_amount_collected);
        });

        $settlement = DB::transaction(function () use (
            $agent, $periodStart, $periodEnd, $collected, $earningsOwed, $netToRemit, $assignments,
            $hasDiscrepancy, $discrepancyNotes, $discrepancyAmountCents
        ) {
            $s = DeliveryAgentCodSettlement::create([
                'agent_id'                   => $agent->id,
                'period_start'               => $periodStart,
                'period_end'                 => $periodEnd,
                'total_cod_collected'  => $collected,
                'total_earnings_owed'  => $earningsOwed,
                'net_to_remit'         => $netToRemit,
                'status'                     => DeliveryAgentCodSettlementStatus::Pending,
                'has_collection_discrepancy' => $hasDiscrepancy,
                'discrepancy_notes'          => $hasDiscrepancy ? $discrepancyNotes : null,
                'discrepancy_amount'   => $discrepancyAmountCents,
                'discrepancy_resolution'     => $hasDiscrepancy ? CodSettlementDiscrepancyResolution::Pending : null,
            ]);

            // Link all covered assignments to this settlement
            DeliveryAssignment::whereIn('id', $assignments->pluck('id'))
                ->update(['cod_settlement_id' => $s->id]);

            return $s;
        });

        $message = __('admin.cod_settlements_section.generated_message', ['amount' => number_format($netToRemit / 100, 2)]);
        if ($hasDiscrepancy) {
            $message .= __('admin.cod_settlements_section.discrepancy_flagged', ['amount' => number_format($discrepancyAmountCents / 100, 2)]);
        }

        return response()->json([
            'success'     => true,
            'message'     => $message,
            'settlement'  => [
                'id'                         => $settlement->id,
                'net_to_remit'               => $netToRemit,
                'has_collection_discrepancy' => $hasDiscrepancy,
                'show_url'                   => route('admin.delivery.cod-settlements.show', $settlement),
            ],
        ]);
    }

    // ── Mark Settled ──────────────────────────────────────────────────────────

    public function markSettled(Request $request, DeliveryAgentCodSettlement $settlement): JsonResponse
    {
        if ($settlement->status !== DeliveryAgentCodSettlementStatus::Pending) {
            return response()->json(['success' => false, 'message' => __('admin.cod_settlements_section.only_pending_can_be_settled')], 422);
        }

        $toleranceCents = 100; // 1 unit tolerance

        $rules = [
            'payment_reference'        => ['required', 'string', 'max:255'],
            'actual_amount_remitted'   => ['required', 'integer', 'min:0'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ];

        $discrepancy = abs($request->input('actual_amount_remitted', 0) - $settlement->net_to_remit);
        if ($discrepancy > $toleranceCents) {
            $rules['notes'] = ['required', 'string', 'max:1000'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($settlement, $validated) {
            $settlement->update([
                'status'     => DeliveryAgentCodSettlementStatus::Settled,
                'settled_at' => now(),
                'notes'      => isset($validated['notes'])
                    ? ($validated['payment_reference'] . ' | ' . $validated['notes'])
                    : $validated['payment_reference'],
            ]);

            // Approve earnings for this period
            DeliveryAgentEarning::where('agent_id', $settlement->agent_id)
                ->whereBetween('created_at', [$settlement->period_start . ' 00:00:00', $settlement->period_end . ' 23:59:59'])
                ->where('status', DeliveryAgentEarningStatus::Pending)
                ->update(['status' => DeliveryAgentEarningStatus::Approved]);

            // Unlock vendor payouts for all COD sub_orders covered by this settlement.
            $subOrderIds = DeliveryAssignment::where('agent_id', $settlement->agent_id)
                ->whereBetween('delivered_at', [$settlement->period_start . ' 00:00:00', $settlement->period_end . ' 23:59:59'])
                ->whereNotNull('cod_amount_collected')
                ->pluck('sub_order_id');

            if ($subOrderIds->isNotEmpty()) {
                SubOrder::whereIn('id', $subOrderIds)
                    ->where('cod_remittance_confirmed', false)
                    ->update([
                        'cod_remittance_confirmed'    => true,
                        'cod_remittance_confirmed_at' => now(),
                        'cod_settlement_id'           => $settlement->id,
                    ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('admin.cod_settlements_section.settled_success'),
        ]);
    }

    // ── Dispute ───────────────────────────────────────────────────────────────

    public function dispute(Request $request, DeliveryAgentCodSettlement $settlement): JsonResponse
    {
        if ($settlement->status === DeliveryAgentCodSettlementStatus::Settled) {
            return response()->json(['success' => false, 'message' => __('admin.cod_settlements_section.already_settled_cannot_dispute')], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $settlement->update([
            'status' => DeliveryAgentCodSettlementStatus::Disputed,
            'notes'  => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.cod_settlements_section.disputed_success'),
        ]);
    }
}
