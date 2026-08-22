<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CustomerStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case Deleted = 'deleted';
}
