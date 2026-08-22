<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WarehouseType: string
{
    use EnumHelpers;

    case PlatformFbn = 'platform_fbn';
    case SellerOwned = 'seller_owned';
    case ThirdParty = 'third_party';
}
