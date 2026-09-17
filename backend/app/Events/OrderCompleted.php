<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * enhancement.md P-08: fired by OrderStateMachine::transition() when the
 * order-level rollup reaches 'completed'. Payout eligibility (P-11) and
 * loyalty points earned are future prompts' listeners — this prompt only
 * fires the event.
 */
class OrderCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order
    ) {
    }
}
