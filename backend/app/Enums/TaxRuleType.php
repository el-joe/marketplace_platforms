<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TaxRuleType: string
{
    use EnumHelpers;

    case Vat = 'vat';
    case Gst = 'gst';
    case SalesTax = 'sales_tax';
}
