<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentCodSettlementStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Settled = 'settled';
    case Disputed = 'disputed';
}
