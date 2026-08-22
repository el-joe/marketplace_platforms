<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdCreativeStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
