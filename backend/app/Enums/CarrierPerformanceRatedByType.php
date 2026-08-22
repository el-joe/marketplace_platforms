<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CarrierPerformanceRatedByType: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Vendor = 'vendor';
}
