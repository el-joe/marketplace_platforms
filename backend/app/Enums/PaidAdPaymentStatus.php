<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdPaymentStatus: string
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Reserved = 'reserved';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
