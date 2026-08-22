<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InboundShipmentStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Receiving = 'receiving';
    case Received = 'received';
    case Rejected = 'rejected';
}
