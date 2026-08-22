<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SecretPromotionStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
}
