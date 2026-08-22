<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdminInvitationCommissionType: string
{
    use EnumHelpers;

    case Percentage = 'percentage';
    case FlatPerConversion = 'flat_per_conversion';
    case FlatPerClick = 'flat_per_click';

    /**
     * marketer_campaigns.commission_type has no "flat_per_conversion" case —
     * it uses "flat_per_order" for the same per-sale semantics.
     */
    public function toCommissionType(): CommissionType
    {
        return match ($this) {
            self::Percentage => CommissionType::Percentage,
            self::FlatPerConversion => CommissionType::FlatPerOrder,
            self::FlatPerClick => CommissionType::FlatPerClick,
        };
    }
}
