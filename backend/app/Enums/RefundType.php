<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RefundType: string
{
    use EnumHelpers;

    case Full = 'full';
    case Partial = 'partial';
    case ShippingOnly = 'shipping_only';
}
