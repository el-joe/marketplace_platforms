<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CarrierClaimType: string
{
    use EnumHelpers;

    case Lost = 'lost';
    case Damaged = 'damaged';
    case Delayed = 'delayed';
    case WrongItem = 'wrong_item';
    case Other = 'other';
}
