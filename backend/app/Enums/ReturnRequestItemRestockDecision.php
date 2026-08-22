<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestItemRestockDecision: string
{
    use EnumHelpers;

    case Restock = 'restock';
    case Dispose = 'dispose';
    case ReturnToSeller = 'return_to_seller';
    case Liquidate = 'liquidate';
}
