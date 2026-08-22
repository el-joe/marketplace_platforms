<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PayoutSchedule: string
{
    use EnumHelpers;

    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
}
