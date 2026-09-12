<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CurrencySymbolType: string
{
    use EnumHelpers;

    case Text = 'text';
    case Image = 'image';
}
