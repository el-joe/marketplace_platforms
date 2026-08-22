<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdCampaignType: string
{
    use EnumHelpers;

    case Cpc = 'cpc';
    case Cpm = 'cpm';
}
