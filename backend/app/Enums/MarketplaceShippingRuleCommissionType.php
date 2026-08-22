<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * commission_type on marketplace_shipping_rules — value set ('fixed',
 * 'percentage', 'mixed') differs from App\Enums\CommissionType
 * ('percentage', 'flat_per_order', 'flat_per_click'), so it cannot be reused.
 */
enum MarketplaceShippingRuleCommissionType: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Mixed = 'mixed';
}
