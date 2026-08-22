<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DisputeMessageSenderRole: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Seller = 'seller';
    case Admin = 'admin';
}
