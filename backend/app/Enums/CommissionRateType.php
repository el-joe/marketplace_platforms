<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CommissionRateType: string
{
    use EnumHelpers;

    case Flat = 'flat';
    case Tiered = 'tiered';
}
