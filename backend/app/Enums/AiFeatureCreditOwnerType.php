<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AiFeatureCreditOwnerType: string
{
    use EnumHelpers;

    case Vendor = 'vendor';
}
