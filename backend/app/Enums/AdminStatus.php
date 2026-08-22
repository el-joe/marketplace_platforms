<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdminStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
}
