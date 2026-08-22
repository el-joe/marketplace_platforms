<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ShipmentTrackingEventStatus: string
{
    use EnumHelpers;

    case LabelCreated = 'label_created';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';
}
