<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorAdminRole: string
{
    use EnumHelpers;

    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
}
