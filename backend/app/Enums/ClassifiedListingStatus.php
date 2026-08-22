<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ClassifiedListingStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingContract = 'pending_contract';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Paused = 'paused';
    case Sold = 'sold';
    case Expired = 'expired';
    case Rejected = 'rejected';
}
