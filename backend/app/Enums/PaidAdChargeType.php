<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdChargeType: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Cpm = 'cpm';
    case Cpc = 'cpc';
    case BudgetReserve = 'budget_reserve';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
