<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum LanguageDirection: string
{
    use EnumHelpers;

    case Ltr = 'ltr';
    case Rtl = 'rtl';
}
