<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TravelPackageStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case SoldOut = 'sold_out';
    case Expired = 'expired';
}
