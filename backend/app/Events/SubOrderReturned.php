<?php

namespace App\Events;

use App\Models\SubOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * enhancement.md P-08: fired by OrderStateMachine::transition() when a
 * sub-order reaches 'returned' (e.g. RTO after a failed delivery).
 */
class SubOrderReturned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SubOrder $subOrder
    ) {
    }
}
