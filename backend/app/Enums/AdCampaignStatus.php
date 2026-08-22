<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdCampaignStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Paused = 'paused';
    case BudgetExhausted = 'budget_exhausted';
    case Ended = 'ended';
    case Rejected = 'rejected';
}
