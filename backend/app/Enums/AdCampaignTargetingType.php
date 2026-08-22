<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdCampaignTargetingType: string
{
    use EnumHelpers;

    case Auto = 'auto';
    case Keyword = 'keyword';
    case Category = 'category';
    case Mixed = 'mixed';
}
