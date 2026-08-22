<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorListingStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Paused = 'paused';
    case Rejected = 'rejected';
    case OutOfStock = 'out_of_stock';
    case Archived = 'archived';
}
