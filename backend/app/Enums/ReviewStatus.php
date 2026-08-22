<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReviewStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Published = 'published';
    case Flagged = 'flagged';
    case AutoFlagged = 'auto_flagged';
    case Hidden = 'hidden';
    case Rejected = 'rejected';
}
