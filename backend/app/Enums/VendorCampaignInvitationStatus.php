<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorCampaignInvitationStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
