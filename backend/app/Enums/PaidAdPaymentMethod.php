<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdPaymentMethod: string
{
    use EnumHelpers;

    case Wallet = 'wallet';
    case PayoutDeduction = 'payout_deduction';
    case Offline = 'offline';
}
