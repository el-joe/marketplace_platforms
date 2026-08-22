<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SubOrderStatus: string
{
    use EnumHelpers;

    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';
}
