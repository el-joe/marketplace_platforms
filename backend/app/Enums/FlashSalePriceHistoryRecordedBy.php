<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FlashSalePriceHistoryRecordedBy: string
{
    use EnumHelpers;

    case System = 'system';
    case Admin = 'admin';
}
