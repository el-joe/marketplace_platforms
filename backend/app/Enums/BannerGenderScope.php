<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BannerGenderScope: string
{
    use EnumHelpers;

    case All = 'all';
    case Male = 'male';
    case Female = 'female';
}
