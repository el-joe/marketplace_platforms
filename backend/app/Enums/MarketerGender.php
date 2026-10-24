<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MarketerGender: string
{
    use EnumHelpers;

    case Male = 'male';
    case Female = 'female';
}
