<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CouponShippingTypeRestriction: string
{
    use EnumHelpers;

    case All = 'all';
    case Fbn = 'fbn';
    case Fbp = 'fbp';
    case Fbm = 'fbm';
}
