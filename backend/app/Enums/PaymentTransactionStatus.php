<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaymentTransactionStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
