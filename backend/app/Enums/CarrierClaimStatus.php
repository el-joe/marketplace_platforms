<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CarrierClaimStatus: string
{
    use EnumHelpers;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Compensated = 'compensated';
}
