<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaymentTransactionType: string
{
    use EnumHelpers;

    case Authorization = 'authorization';
    case Capture = 'capture';
    case Sale = 'sale';
    case Refund = 'refund';
    case Void = 'void';
    case Chargeback = 'chargeback';
}
