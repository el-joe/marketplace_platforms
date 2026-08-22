<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WalletWithdrawalRequestStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Processed = 'processed';
    case Rejected = 'rejected';
}
