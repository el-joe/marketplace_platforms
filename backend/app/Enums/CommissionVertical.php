<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Commission verticals ("category types") a campaign or commission rule can belong to.
 * Labels live in lang/{locale}/enums/commission_vertical.php.
 */
enum CommissionVertical: string
{
    use EnumHelpers;

    case Product = 'product';
    case Classified = 'classified';
    case Travel = 'travel';

    /** @return array<string, string> value => localized label */
    public static function labels(): array
    {
        return self::options();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
