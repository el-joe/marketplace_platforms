<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WalletTransactionType: string
{
    use EnumHelpers;

    case Credit = 'credit';
    case Debit = 'debit';
}
