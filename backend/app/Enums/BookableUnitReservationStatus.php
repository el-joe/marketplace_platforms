<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BookableUnitReservationStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
