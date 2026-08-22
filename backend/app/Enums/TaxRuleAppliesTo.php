<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TaxRuleAppliesTo: string
{
    use EnumHelpers;

    case Product = 'product';
    case Shipping = 'shipping';
    case Both = 'both';
}
