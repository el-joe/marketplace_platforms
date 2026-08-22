<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdCampaignKeywordMatchType: string
{
    use EnumHelpers;

    case Broad = 'broad';
    case Phrase = 'phrase';
    case Exact = 'exact';
}
