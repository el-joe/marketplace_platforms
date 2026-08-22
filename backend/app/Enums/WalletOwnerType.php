<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WalletOwnerType: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Vendor = 'vendor';
    case DeliveryAgent = 'delivery_agent';
    case TravelAgency = 'travel_agency';
}
