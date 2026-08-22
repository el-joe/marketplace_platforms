<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdBookingStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}
