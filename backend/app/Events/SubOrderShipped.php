<?php

namespace App\Events;

use App\Models\SubOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * enhancement.md P-08: fired by OrderStateMachine::transition() when a
 * sub-order reaches 'shipped'.
 */
class SubOrderShipped
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SubOrder $subOrder
    ) {
    }
}
