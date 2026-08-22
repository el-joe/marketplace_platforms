<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorSubscriptionInvoiceStatus: string
{
    use EnumHelpers;

    case Paid = 'paid';
    case Open = 'open';
    case Void = 'void';
    case Uncollectible = 'uncollectible';
}
