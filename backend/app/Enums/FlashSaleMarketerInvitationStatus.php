<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FlashSaleMarketerInvitationStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
