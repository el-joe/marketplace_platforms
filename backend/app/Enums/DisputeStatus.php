<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DisputeStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case SellerResponded = 'seller_responded';
    case UnderReview = 'under_review';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
