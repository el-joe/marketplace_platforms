<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestInspectionResult: string
{
    use EnumHelpers;

    case Good = 'good';
    case Damaged = 'damaged';
    case Missing = 'missing';
    case Counterfeit = 'counterfeit';
}
