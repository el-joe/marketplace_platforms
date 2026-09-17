<?php

namespace App\Events;

use App\Models\SubOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * enhancement.md P-08: fired by OrderStateMachine::transition() when a
 * sub-order reaches 'delivered'. Listened to by CaptureCodOnDelivery (this
 * prompt). Also the hook P-09 (warranty activation) and P-12 (marketer
 * conversion approval) will attach their own listeners to later — this
 * prompt only fires the event, it does not implement that logic.
 */
class SubOrderDelivered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SubOrder $subOrder
    ) {
    }
}
