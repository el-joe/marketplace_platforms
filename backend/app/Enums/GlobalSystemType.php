<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum GlobalSystemType: string
{
    use EnumHelpers;

    case ExpressFbn = 'express_fbn';
    case MerchantFbp = 'merchant_fbp';
    case Marketplace = 'marketplace';
}
