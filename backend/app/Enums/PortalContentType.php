<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PortalContentType: string
{
    use EnumHelpers;

    case Text = 'text';
    case RichText = 'richtext';
    case Link = 'link';
    case Image = 'image';
}
