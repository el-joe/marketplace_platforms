<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RadioScheduleSlotRecurrence: string
{
    use EnumHelpers;

    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
