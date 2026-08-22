<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CodSettlementDiscrepancyResolution: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case DeductedFromEarnings = 'deducted_from_earnings';
    case WrittenOff = 'written_off';
    case VendorChargeback = 'vendor_chargeback';
}
