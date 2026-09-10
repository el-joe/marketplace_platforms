<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdSlotTargetType: string
{
    use EnumHelpers;

    case Placement = 'placement';
    case PageBlock = 'page_block';
}
