<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case OnShift = 'on_shift';
    case Suspended = 'suspended';
}
