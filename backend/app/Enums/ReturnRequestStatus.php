<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestStatus: string
{
    use EnumHelpers;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AwaitingPickup = 'awaiting_pickup';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Inspecting = 'inspecting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
