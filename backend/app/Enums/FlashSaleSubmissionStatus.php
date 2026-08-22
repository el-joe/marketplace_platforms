<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FlashSaleSubmissionStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Live = 'live';
    case SoldOut = 'sold_out';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Ended = 'ended';
}
