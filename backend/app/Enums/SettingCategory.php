<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SettingCategory: string
{
    use EnumHelpers;

    case Appearance = 'appearance';
    case Customers = 'customers';
    case General = 'general';
    case Integrations = 'integrations';
    case Marketer = 'marketer';
    case Notifications = 'notifications';
    case Orders = 'orders';
    case Security = 'security';
    case Vendors = 'vendors';
}
