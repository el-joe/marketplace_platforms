<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdAdvertiserType: string
{
    use EnumHelpers;

    case Vendor = 'vendor';
    case Marketer = 'marketer';
}
