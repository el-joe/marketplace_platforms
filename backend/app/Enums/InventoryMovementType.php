<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InventoryMovementType: string
{
    use EnumHelpers;

    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Reservation = 'reservation';
    case Release = 'release';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case ReturnMovement = 'return';
    case Transfer = 'transfer';
}
