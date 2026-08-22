<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TravelPackageInquiryStatus: string
{
    use EnumHelpers;

    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Closed = 'closed';
}
