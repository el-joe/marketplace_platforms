<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorDocumentRequirementLevel: string
{
    use EnumHelpers;

    case Mandatory = 'mandatory';
    case Optional = 'optional';
    case NotApplicable = 'not_applicable';
}
