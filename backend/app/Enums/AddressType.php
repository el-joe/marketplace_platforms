<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AddressType: string
{
    use EnumHelpers;

    case Shipping = 'shipping';
    case Billing = 'billing';
    case Both = 'both';
}
