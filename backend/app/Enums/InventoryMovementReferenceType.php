<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InventoryMovementReferenceType: string
{
    use EnumHelpers;

    case Order = 'order';
    case InboundShipment = 'inbound_shipment';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case ReturnRequest = 'return';
    case SubOrder = 'sub_order';
    case CampaignSample = 'campaign_sample';
    case WarrantyReplacement = 'warranty_replacement';
    case Rto = 'rto';
}
