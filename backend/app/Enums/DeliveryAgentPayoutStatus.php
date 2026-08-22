<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentPayoutStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Failed = 'failed';
}
