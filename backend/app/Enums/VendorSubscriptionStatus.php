<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorSubscriptionStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PastDue = 'past_due';
    case Trialing = 'trialing';
}
