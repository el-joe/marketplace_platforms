<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorStrikeReason: string
{
    use EnumHelpers;

    case LateShipment = 'late_shipment';
    case PoorQuality = 'poor_quality';
    case CustomerComplaint = 'customer_complaint';
    case PolicyViolation = 'policy_violation';
    case Other = 'other';
}
