<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

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
