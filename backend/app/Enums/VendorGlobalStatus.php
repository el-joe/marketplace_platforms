<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorGlobalStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Blacklisted = 'blacklisted';
    case UnderReview = 'under_review';
}
