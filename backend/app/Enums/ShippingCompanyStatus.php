<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ShippingCompanyStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
