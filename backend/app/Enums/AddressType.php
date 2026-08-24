<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AddressType: string
{
    use EnumHelpers;

    case Home = 'home';
    case Work = 'work';
    case Shipping = 'shipping';
    case Billing = 'billing';
    case Both = 'both';
}
