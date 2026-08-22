<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum NotificationChannel: string
{
    use EnumHelpers;

    case Database = 'database';
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';
    case Whatsapp = 'whatsapp';
}
