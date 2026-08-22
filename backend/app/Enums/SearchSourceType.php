<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SearchSourceType: string
{
    use EnumHelpers;

    case Product = 'product';
    case Classified = 'classified';
    case Travel = 'travel';
    case All = 'all';
}
