<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentType: string
{
    use EnumHelpers;

    case Platform = 'platform';
    case ThirdParty = 'third_party';
}
