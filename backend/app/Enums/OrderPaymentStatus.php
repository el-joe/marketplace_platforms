<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum OrderPaymentStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
