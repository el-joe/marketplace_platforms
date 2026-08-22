<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum OrderItemFulfillmentStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Picked = 'picked';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
