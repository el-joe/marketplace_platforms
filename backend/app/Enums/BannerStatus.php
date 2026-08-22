<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BannerStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
}
