<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TravelAgencyStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
