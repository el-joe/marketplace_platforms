<?php

namespace App\Jobs;

use App\Enums\DeliveryAgentStatus;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentCodSettlement;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCodSettlementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $generated = 0;

        DeliveryAgent::where('status', DeliveryAgentStatus::Active)
            ->chunkById(50, function ($agents) use (&$generated) {
                foreach ($agents as $agent) {
                    $uncoveredAssignments = DeliveryAssignment::with('subOrder.order')
                        ->where('agent_id', $agent->id)
                        ->whereNotNull('cod_amount_collected')
                        ->whereNull('cod_settlement_id')
                        ->where('delivered_at', '<', now()->startOfDay())
                        ->get();

                    if ($uncoveredAssignments->isEmpty()) {
                        continue;
                    }

                    $periodStart = $uncoveredAssignments->min('delivered_at')->toDateString();
                    $periodEnd   = $uncoveredAssignments->max('delivered_at')->toDateString();

                    $totalCollected = $uncoveredAssignments->sum('cod_amount_collected');

                    $totalEarnings = DeliveryAgentEarning::where('agent_id', $agent->id)
                        ->whereIn('delivery_assignment_id', $uncoveredAssignments->pluck('id'))
                        ->where('status', '!=', 'cancelled')
                        ->sum('amount');

                    // Aggregate per-assignment discrepancies into the settlement record.
                    $discrepancyAssignments = $uncoveredAssignments->filter(
                        fn($a) => ! empty($a->discrepancy_note)
                    );
                    $hasDiscrepancy      = $discrepancyAssignments->isNotEmpty();
                    $discrepancyNotes    = $discrepancyAssignments
                        ->map(fn($a) => "Assignment {$a->id}: {$a->discrepancy_note}")
                        ->implode("\n");
                    $discrepancyAmountCents = $discrepancyAssignments->sum(function ($a) {
                        // Recalculate shortfall per assignment using the sub-order's order total.
                        $expected = (int) ($a->subOrder?->order?->total ?? 0);
                        return max(0, $expected - (int) $a->cod_amount_collected);
                    });

                    $settlement = DeliveryAgentCodSettlement::create([
                        'agent_id'                   => $agent->id,
                        'period_start'               => $periodStart,
                        'period_end'                 => $periodEnd,
                        'total_cod_collected'  => $totalCollected,
                        'total_earnings_owed'  => $totalEarnings,
                        'net_to_remit'         => max(0, $totalCollected - $totalEarnings),
                        'status'                     => 'pending',
                        'has_collection_discrepancy' => $hasDiscrepancy,
                        'discrepancy_notes'          => $hasDiscrepancy ? $discrepancyNotes : null,
                        'discrepancy_amount'   => $discrepancyAmountCents,
                        'discrepancy_resolution'     => $hasDiscrepancy ? 'pending' : null,
                    ]);

                    $uncoveredAssignments->each(fn($a) => $a->update(['cod_settlement_id' => $settlement->id]));

                    $generated++;

                    Log::info('COD settlement generated', [
                        'settlement_id' => $settlement->id,
                        'agent_id'      => $agent->id,
                        'assignments'   => $uncoveredAssignments->count(),
                        'net_to_remit'  => $settlement->net_to_remit,
                    ]);
                }
            });

        Log::info("GenerateCodSettlementsJob completed: {$generated} settlements created.");
    }
}
