<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorType: string
{
    use EnumHelpers;

    case ProductVendor = 'product_vendor';
    case ClassifiedVendor = 'classified_vendor';
}
