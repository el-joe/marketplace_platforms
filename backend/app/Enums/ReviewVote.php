<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReviewVote: string
{
    use EnumHelpers;

    case Helpful = 'helpful';
    case NotHelpful = 'not_helpful';
}
