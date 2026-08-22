<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PageStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';
}
