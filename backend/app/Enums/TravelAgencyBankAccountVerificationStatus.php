<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TravelAgencyBankAccountVerificationStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
