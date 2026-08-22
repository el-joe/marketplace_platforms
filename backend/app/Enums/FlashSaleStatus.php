<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FlashSaleStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case SubmissionOpen = 'submission_open';
    case SubmissionClosed = 'submission_closed';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
