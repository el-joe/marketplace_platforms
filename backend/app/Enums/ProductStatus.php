<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ProductStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Active = 'active';
    case Discontinued = 'discontinued';
    case Restricted = 'restricted';
}
