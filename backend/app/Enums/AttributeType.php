<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttributeType: string
{
    use EnumHelpers;

    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Color = 'color';
}
