<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ShipmentTrackingEventStatus: string
{
    use EnumHelpers;

    case LabelCreated = 'label_created';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    // International multi-leg journey events (docs/plans/international_product_shipping.md
    // Phase 4, design decision #6) — recorded via InternationalTrackingService::recordLeg()
    // for corridors where a customs/linehaul leg runs under a different carrier's tracking
    // number than shipments.tracking_number.
    case ExportScan = 'export_scan';
    case CustomsCleared = 'customs_cleared';
    case CustomsHold = 'customs_hold';
    case Linehaul = 'linehaul';
    case ImportScan = 'import_scan';
}
