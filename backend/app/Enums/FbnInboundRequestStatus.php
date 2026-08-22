<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FbnInboundRequestStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Shipped = 'shipped';
    case Received = 'received';
    case Rejected = 'rejected';
}
