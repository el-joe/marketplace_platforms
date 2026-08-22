<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdSlotPricingModel: string
{
    use EnumHelpers;

    case FixedWeekly = 'fixed_weekly';
    case FixedMonthly = 'fixed_monthly';
    case Cpm = 'cpm';
    case Cpc = 'cpc';
}
