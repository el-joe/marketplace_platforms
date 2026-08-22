<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RefundStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
