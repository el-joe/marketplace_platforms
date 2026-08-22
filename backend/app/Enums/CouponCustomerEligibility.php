<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CouponCustomerEligibility: string
{
    use EnumHelpers;

    case All = 'all';
    case NewCustomers = 'new_customers';
    case SpecificSegment = 'specific_segment';
    case SpecificUsers = 'specific_users';
}
