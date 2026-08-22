<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdSupportArticleStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
}
