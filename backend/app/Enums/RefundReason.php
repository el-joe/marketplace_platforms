<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RefundReason: string
{
    use EnumHelpers;

    case CustomerRequest = 'customer_request';
    case OutOfStock = 'out_of_stock';
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case LateDelivery = 'late_delivery';
    case DuplicateOrder = 'duplicate_order';
    case Other = 'other';
}
