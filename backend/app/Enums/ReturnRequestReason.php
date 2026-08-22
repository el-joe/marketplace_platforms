<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnRequestReason: string
{
    use EnumHelpers;

    case ChangedMind = 'changed_mind';
    case WrongItem = 'wrong_item';
    case Defective = 'defective';
    case Damaged = 'damaged';
    case NotAsDescribed = 'not_as_described';
    case SizeIssue = 'size_issue';
    case QualityIssue = 'quality_issue';
    case ArrivedLate = 'arrived_late';
    case Other = 'other';
}
