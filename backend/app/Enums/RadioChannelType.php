<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RadioChannelType: string
{
    use EnumHelpers;

    case Audio = 'audio';
    case Video = 'video';
}
