<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PackagingSupplyRequestStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Rejected = 'rejected';
}
