<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeviceTokenPlatform: string
{
    use EnumHelpers;

    case Ios = 'ios';
    case Android = 'android';
}
