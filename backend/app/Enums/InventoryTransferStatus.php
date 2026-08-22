<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InventoryTransferStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
