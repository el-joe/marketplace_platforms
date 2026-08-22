<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorDocumentStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
