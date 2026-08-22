<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CouponScope: string
{
    use EnumHelpers;

    case Platform = 'platform';
    case Vendor = 'vendor';
    case Category = 'category';
    case Product = 'product';
}
