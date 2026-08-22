<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdvertiseInquiryStatus: string
{
    use EnumHelpers;

    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';
}
