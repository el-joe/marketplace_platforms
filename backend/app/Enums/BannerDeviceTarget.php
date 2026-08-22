<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BannerDeviceTarget: string
{
    use EnumHelpers;

    case All = 'all';
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case App = 'app';
}
