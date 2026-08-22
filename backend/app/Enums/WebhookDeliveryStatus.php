<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WebhookDeliveryStatus: string
{
    use EnumHelpers;

    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case Retry = 'retry';
}
