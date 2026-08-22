<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CouponType: string
{
    use EnumHelpers;

    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FreeShipping = 'free_shipping';
    case Bogo = 'bogo';
}
