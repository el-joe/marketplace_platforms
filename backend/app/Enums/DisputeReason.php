<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DisputeReason: string
{
    use EnumHelpers;

    case ItemNotReceived = 'item_not_received';
    case ItemDamaged = 'item_damaged';
    case ItemNotAsDescribed = 'item_not_as_described';
    case Counterfeit = 'counterfeit';
    case WrongItem = 'wrong_item';
    case QualityIssue = 'quality_issue';
    case SellerUnresponsive = 'seller_unresponsive';
    case RefundNotReceived = 'refund_not_received';
    case Other = 'other';
}
