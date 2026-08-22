<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FbnStorageFeeStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
}
