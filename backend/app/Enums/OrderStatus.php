<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum OrderStatus: string
{
    use EnumHelpers;

    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case PartiallyShipped = 'partially_shipped';
    case Shipped = 'shipped';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
}
