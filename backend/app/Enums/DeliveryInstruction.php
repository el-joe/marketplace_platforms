<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryInstruction: string
{
    use EnumHelpers;

    case GetItemsTogether = 'get_items_together';
    case LeaveAtDoor = 'leave_at_door';
}
