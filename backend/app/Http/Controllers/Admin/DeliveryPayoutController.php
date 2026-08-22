<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Enums\DeliveryAgentEarningStatus;
use App\Enums\DeliveryAgentPayoutStatus;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentPayout;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeliveryPayoutController extends Controller
{
    use HasDataTable;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $columns = [
            ['orderable_column' => 'delivery_agent_payouts.payout_number'],
            ['searchable_columns' => ['delivery_agents.name'], 'orderable_column' => 'delivery_agents.name'],
            ['orderable_column' => 'delivery_agent_payouts.period_start'],
            ['orderable_column' => 'delivery_agent_payouts.net_amount'],
            ['orderable_column' => 'delivery_agent_payouts.status'],
            ['orderable_column' => 'delivery_agent_payouts.processed_at'],
            [],
        ];

        $pendingCount = DeliveryAgentPayout::where('status', DeliveryAgentPayoutStatus::Pending)->count();

        $stats = [
            'total'    => DeliveryAgentPayout::count(),
            'pending'  => $pendingCount,
            'approved' => DeliveryAgentPayout::where('status', DeliveryAgentPayoutStatus::Approved)->count(),
            'paid'     => DeliveryAgentPayout::where('status', DeliveryAgentPayoutStatus::Paid)->count(),
        ];

        $agents = DeliveryAgent::orderBy('name')->get(['id', 'name']);
        $currencies = Currency::where('is_active', true)->orderBy('code')->pluck('code');

        return view('admin.delivery.payouts.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Delivery'],
                ['label' => 'Payouts'],
            ],
            'pendingCount' => $pendingCount,
            'stats'        => $stats,
            'agents'       => $agents,
            'currencies'   => $currencies,
        ]);
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['orderable_column' => 'delivery_agent_payouts.payout_number'],
            ['searchable_columns' => ['delivery_agents.name'], 'orderable_column' => 'delivery_agents.name'],
            ['orderable_column' => 'delivery_agent_payouts.period_start'],
            ['orderable_column' => 'delivery_agent_payouts.net_amount'],
            ['orderable_column' => 'delivery_agent_payouts.status'],
            ['orderable_column' => 'delivery_agent_payouts.processed_at'],
            [],
        ];

        $query = DeliveryAgentPayout::query()
            ->join('delivery_agents', 'delivery_agents.id', '=', 'delivery_agent_payouts.agent_id')
            ->select([
                'delivery_agent_payouts.*',
                'delivery_agents.name as agent_name',
            ]);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('delivery_agent_payouts.status', $v),
            'agent_id' => fn($q, $v) => $q->where('delivery_agent_payouts.agent_id', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function (DeliveryAgentPayout $payout) {
            return [
                'id' => $payout->id,
                'payout_number' => $payout->payout_number,
                'agent_name' => e($payout->agent_name),
                'period' => $payout->period_start->format('d M') . ' – ' . $payout->period_end->format('d M Y'),
                'total_deliveries' => $payout->total_deliveries,
                'gross_earnings' => number_format($payout->gross_earnings, 2),
                'net_amount' => number_format($payout->net_amount, 2),
                'currency' => $payout->currency,
                'status' => $payout->status->value,
                'status_label' => $payout->status->label(),
                'processed_at' => $payout->processed_at?->format('d M Y H:i') ?? '—',
            ];
        });
    }

    // ── Generate ──────────────────────────────────────────────────────────────

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'agent_id' => ['required', 'exists:delivery_agents,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $agentId = $request->input('agent_id');

        // Group by the earnings' own currency — one payout row per currency.
        $rows = DB::table('delivery_agent_earnings')
            ->where('agent_id', $agentId)
            ->where('status', DeliveryAgentEarningStatus::Approved->value)
            ->whereBetween('created_at', [$request->period_start . ' 00:00:00', $request->period_end . ' 23:59:59'])
            ->selectRaw('
                currency,
                COUNT(DISTINCT delivery_assignment_id) as total_deliveries,
                SUM(CASE WHEN earning_type != ? THEN amount ELSE 0 END) as gross,
                SUM(CASE WHEN earning_type = ? THEN ABS(amount) ELSE 0 END) as deductions
            ', ['deduction', 'deduction'])
            ->groupBy('currency')
            ->get();

        $rows = $rows->filter(fn($r) => $r->gross > 0);

        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No approved earnings found for the selected period.'], 422);
        }

        $payouts = [];

        foreach ($rows as $row) {
            $gross      = (int) $row->gross;
            $deductions = (int) $row->deductions;
            $net        = $gross - $deductions;

            $payout = DeliveryAgentPayout::create([
                'payout_number'        => 'DAP-' . strtoupper(Str::random(8)),
                'agent_id'             => $agentId,
                'period_start'         => $request->period_start,
                'period_end'           => $request->period_end,
                'total_deliveries'     => (int) $row->total_deliveries,
                'gross_earnings' => $gross,
                'deductions'     => $deductions,
                'net_amount'     => $net,
                'currency'             => $row->currency,
                'status'               => DeliveryAgentPayoutStatus::Pending,
            ]);

            $payouts[] = [
                'id'           => $payout->id,
                'payout_number' => $payout->payout_number,
                'net_amount'   => number_format($net, 2),
                'currency'     => $payout->currency,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($payouts) . ' payout(s) generated: ' . implode(', ', array_column($payouts, 'payout_number')),
            'payouts' => $payouts,
        ]);
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(DeliveryAgentPayout $payout): JsonResponse
    {
        if ($payout->status !== DeliveryAgentPayoutStatus::Pending) {
            return response()->json(['success' => false, 'message' => 'Only pending payouts can be approved.'], 422);
        }

        $payout->update([
            'status' => DeliveryAgentPayoutStatus::Approved,
            'approved_by_admin_id' => auth('admin')->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Payout approved.']);
    }

    // ── Process / Mark Paid ────────────────────────────────────────────────────

    public function process(Request $request, DeliveryAgentPayout $payout): JsonResponse
    {
        $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        if ($payout->status !== DeliveryAgentPayoutStatus::Approved) {
            return response()->json(['success' => false, 'message' => 'Payout must be approved before processing.'], 422);
        }

        $payout->update([
            'status' => DeliveryAgentPayoutStatus::Paid,
            'payment_method' => $request->payment_method,
            'payment_reference' => $request->payment_reference,
            'processed_at' => now(),
        ]);

        // Mark underlying earnings as paid
        DB::table('delivery_agent_earnings')
            ->where('agent_id', $payout->agent_id)
            ->where('status', DeliveryAgentEarningStatus::Approved->value)
            ->whereBetween('created_at', [
                $payout->period_start->startOfDay(),
                $payout->period_end->endOfDay(),
            ])
            ->update(['status' => DeliveryAgentEarningStatus::Paid->value, 'paid_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Payout marked as paid.']);
    }
}
