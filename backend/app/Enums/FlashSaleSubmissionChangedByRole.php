<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FlashSaleSubmissionChangedByRole: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Vendor = 'vendor';
    case System = 'system';
}
