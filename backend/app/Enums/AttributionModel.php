<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttributionModel: string
{
    use EnumHelpers;

    case LastClick = 'last_click';
    case FirstClick = 'first_click';
    case Linear = 'linear';
}
