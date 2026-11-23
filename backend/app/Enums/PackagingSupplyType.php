<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * @deprecated Packaging types are admin-managed rows (App\Models\PackagingSupplyType, table packaging_supply_types)
 *             since client item #12. These cases are the original seeded codes, kept for reference only.
 */
enum PackagingSupplyType: string
{
    use EnumHelpers;

    case Box = 'box';
    case Bag = 'bag';
    case Tape = 'tape';
    case Label = 'label';
    case BubbleWrap = 'bubble_wrap';
    case Other = 'other';
}
