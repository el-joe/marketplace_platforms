<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidBannerBookingStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
