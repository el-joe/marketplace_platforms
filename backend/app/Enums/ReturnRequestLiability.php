<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestLiability: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Seller = 'seller';
    case Platform = 'platform';
    case Carrier = 'carrier';
}
