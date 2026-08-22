<?php

namespace App\Services\Carrier;

use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentReassignmentService
{
    // Statuses that allow reassignment — picked_up and beyond are committed
    // deliveries that cannot safely change hands mid-transit.
    private const REASSIGNABLE_STATUSES = [
        DeliveryAssignment::STATUS_ASSIGNED,
        DeliveryAssignment::STATUS_ACCEPTED,
    ];

    /**
     * Reassign an assignment from its current agent to $newAgent.
     *
     * Isolation invariants enforced here (not just in the controller):
     *  - The assignment's current agent must belong to the supervisor's company.
     *  - The new agent must also belong to the SAME company.
     *  - Platform agents (shipping_company_id IS NULL) are excluded on both sides.
     */
    public function reassign(
        DeliveryAssignment $assignment,
        DeliveryAgent $newAgent,
        ShippingCompanySupervisor $actor,
    ): DeliveryAssignment {
        $companyId = $actor->shipping_company_id;
        // Compat shim: falls back to the company's home country until the
        // supervisor is backfilled with their own country_id.
        $countryId = $actor->country_id ?? $actor->company?->country_id;

        // Validate current agent belongs to the supervisor's company.
        $currentAgent = $assignment->agent;
        if ($currentAgent->shipping_company_id !== $companyId) {
            abort(404);
        }

        // Validate new agent belongs to the same company (never platform agents)
        // and, when the supervisor is country-scoped, the same country.
        if ($newAgent->shipping_company_id !== $companyId
            || ($countryId && $newAgent->country_id !== $countryId)
        ) {
            throw ValidationException::withMessages([
                'new_delivery_agent_id' => 'The new agent does not belong to your company.',
            ]);
        }

        if (! in_array($assignment->status, self::REASSIGNABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'assignment' => 'This assignment cannot be reassigned in its current status ('
                    . $assignment->status->value . '). Only assigned or accepted assignments may be reassigned.',
            ]);
        }

        if ($newAgent->id === $currentAgent->id) {
            throw ValidationException::withMessages([
                'new_delivery_agent_id' => 'The new agent is the same as the current agent.',
            ]);
        }

        DB::transaction(function () use ($assignment, $newAgent, $actor, $currentAgent) {
            $assignment->update([
                'agent_id' => $newAgent->id,
                'status'   => DeliveryAssignment::STATUS_ASSIGNED,
            ]);

            // Audit log stored as a JSON note appended to agent_notes.
            $audit = json_encode([
                'event'            => 'reassigned',
                'from_agent_id'    => $currentAgent->id,
                'from_agent_name'  => $currentAgent->name,
                'to_agent_id'      => $newAgent->id,
                'to_agent_name'    => $newAgent->name,
                'by_supervisor_id' => $actor->id,
                'by_supervisor'    => $actor->name,
                'at'               => now()->toIso8601String(),
            ]);

            $existing   = $assignment->agent_notes ?? '';
            $assignment->update([
                'agent_notes' => trim($existing . "\n[REASSIGN] " . $audit),
            ]);
        });

        // Notify the new agent (fire-and-forget; notification class wired separately)
        $newAgent->notify(new \App\Notifications\Carrier\AssignmentReassignedNotification($assignment));

        return $assignment->fresh();
    }
}
