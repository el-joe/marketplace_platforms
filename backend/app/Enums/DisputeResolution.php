<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DisputeResolution: string
{
    use EnumHelpers;

    case FavorCustomer = 'favor_customer';
    case FavorSeller = 'favor_seller';
    case Split = 'split';
    case NoAction = 'no_action';
}
