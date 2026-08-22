<?php

namespace App\Jobs;

use App\Models\DeliveryAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyOperationsTeamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly string $assignmentId) {}

    public function handle(): void
    {
        $assignment = DeliveryAssignment::with(['subOrder.order', 'agent'])->find($this->assignmentId);

        if (! $assignment) {
            Log::warning('NotifyOperationsTeamJob: assignment not found', ['id' => $this->assignmentId]);
            return;
        }

        // Dispatch to operations channel (Slack/email/internal notification).
        // Concrete notification class wired up separately by the ops/infra team.
        Log::info('Delivery failed — ops notification', [
            'assignment_id'  => $assignment->id,
            'agent_id'       => $assignment->agent_id,
            'failure_reason' => $assignment->failure_reason,
            'sub_order_id'   => $assignment->sub_order_id,
        ]);
    }
}
