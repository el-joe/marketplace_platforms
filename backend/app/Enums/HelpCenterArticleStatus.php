<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum HelpCenterArticleStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
}
