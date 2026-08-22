<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AbTestStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Running = 'running';
    case Paused = 'paused';
    case Concluded = 'concluded';
}
