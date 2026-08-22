<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ProductViewSource: string
{
    use EnumHelpers;

    case Search = 'search';
    case Category = 'category';
    case Recommendation = 'recommendation';
    case Direct = 'direct';
    case Ad = 'ad';
    case Social = 'social';
}
