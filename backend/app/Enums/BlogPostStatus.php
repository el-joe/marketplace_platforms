<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BlogPostStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
