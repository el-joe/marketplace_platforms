<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorCommissionDiscountType: string
{
    use EnumHelpers;

    case None = 'none';
    case Flat = 'flat';
    case Percentage = 'percentage';
}
