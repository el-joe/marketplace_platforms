<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AiImageEnhancementJobStatus: string
{
    use EnumHelpers;

    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
