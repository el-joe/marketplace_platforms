<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorStrikeSeverity: string
{
    use EnumHelpers;

    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
}
