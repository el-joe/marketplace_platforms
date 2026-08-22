<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReviewVendorReplyStatus: string
{
    use EnumHelpers;

    case Published = 'published';
    case Hidden = 'hidden';
}
