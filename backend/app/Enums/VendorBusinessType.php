<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorBusinessType: string
{
    use EnumHelpers;

    case Individual = 'individual';
    case SoleProp = 'sole_prop';
    case Llc = 'llc';
    case Corp = 'corp';
}
