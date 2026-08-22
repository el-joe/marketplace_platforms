<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentVehicleType: string
{
    use EnumHelpers;

    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case Van = 'van';
    case Bicycle = 'bicycle';
}
