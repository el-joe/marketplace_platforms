<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAssignmentStatus: string
{
    use EnumHelpers;

    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
