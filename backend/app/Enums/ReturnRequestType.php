<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestType: string
{
    use EnumHelpers;

    case Refund = 'refund';
    case Exchange = 'exchange';
    case StoreCredit = 'store_credit';
}
