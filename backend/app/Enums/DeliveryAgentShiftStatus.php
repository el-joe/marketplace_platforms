<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentShiftStatus: string
{
    use EnumHelpers;

    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
