<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestItemCondition: string
{
    use EnumHelpers;

    case New = 'new';
    case Opened = 'opened';
    case Used = 'used';
    case Damaged = 'damaged';
}
