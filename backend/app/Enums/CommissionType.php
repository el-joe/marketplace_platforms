<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CommissionType: string
{
    use EnumHelpers;

    case Percentage = 'percentage';
    case FlatPerOrder = 'flat_per_order';
    case FlatPerClick = 'flat_per_click';
}
