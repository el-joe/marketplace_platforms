<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdSlotPricingModel: string
{
    use EnumHelpers;

    case FixedDaily = 'fixed_daily';
    case FixedWeekly = 'fixed_weekly';
    case FixedMonthly = 'fixed_monthly';
    case Cpm = 'cpm';
    case Cpc = 'cpc';

    public function isFixed(): bool
    {
        return in_array($this, [self::FixedDaily, self::FixedWeekly, self::FixedMonthly], true);
    }

    public function unitDays(): ?int
    {
        return match ($this) {
            self::FixedDaily => 1,
            self::FixedWeekly => 7,
            self::FixedMonthly => 30,
            self::Cpm, self::Cpc => null,
        };
    }
}
