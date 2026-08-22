<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VirtualTryonSessionStatus: string
{
    use EnumHelpers;

    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
